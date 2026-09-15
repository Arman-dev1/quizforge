<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\QuizTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateQuizFromTemplate
{
    /**
     * Instantiate a template into a fresh draft quiz. Logic conditions
     * in the template reference template-local question/option ids, so
     * everything is created first and the conditions are rewritten
     * through old-id → new-id maps afterwards.
     */
    public function handle(User $user, QuizTemplate $template, ?string $name = null): Quiz
    {
        return DB::transaction(function () use ($user, $template, $name) {
            $content = $template->content;
            $quizName = $name ?? $template->name;

            $quiz = Quiz::create([
                'workspace_id' => $user->current_workspace_id,
                'created_by' => $user->id,
                'name' => $quizName,
                'slug' => Quiz::generateSlug($quizName),
                'type' => $template->type,
                'status' => QuizStatus::Draft,
                'settings' => $content['settings'] ?? $template->type->defaultSettings(),
            ]);

            $questionIdMap = [];
            $optionIdMap = [];
            $createdQuestions = [];

            foreach ($content['pages'] ?? [] as $pageIndex => $pageData) {
                $page = $quiz->pages()->create([
                    'title' => $pageData['title'] ?? null,
                    'description' => $pageData['description'] ?? null,
                    'position' => $pageIndex,
                ]);

                foreach ($pageData['questions'] ?? [] as $questionIndex => $questionData) {
                    $question = $page->questions()->create([
                        'quiz_id' => $quiz->id,
                        'type' => $questionData['type'],
                        'title' => $questionData['title'] ?? '',
                        'description' => $questionData['description'] ?? null,
                        'placeholder' => $questionData['placeholder'] ?? null,
                        'help_text' => $questionData['help_text'] ?? null,
                        'is_required' => (bool) ($questionData['is_required'] ?? false),
                        'is_hidden' => (bool) ($questionData['is_hidden'] ?? false),
                        'position' => $questionIndex,
                        'settings' => $questionData['settings'] ?? [],
                        'validation' => $questionData['validation'] ?? [],
                    ]);

                    if (isset($questionData['id'])) {
                        $questionIdMap[(int) $questionData['id']] = $question->id;
                    }

                    foreach ($questionData['options'] ?? [] as $optionIndex => $optionData) {
                        $option = $question->options()->create([
                            'label' => $optionData['label'],
                            'is_correct' => (bool) ($optionData['is_correct'] ?? false),
                            'position' => $optionIndex,
                            // Carries the option's category through, so a
                            // template of a category quiz stays one.
                            'settings' => $optionData['settings'] ?? null,
                        ]);

                        if (isset($optionData['id'])) {
                            $optionIdMap[(int) $optionData['id']] = $option->id;
                        }
                    }

                    if (! empty($questionData['logic']['conditions'])) {
                        $createdQuestions[] = [$question, $questionData['logic']];
                    }
                }
            }

            foreach ($createdQuestions as [$question, $logic]) {
                $conditions = [];

                foreach ($logic['conditions'] as $condition) {
                    $triggerId = $questionIdMap[(int) ($condition['question_id'] ?? 0)] ?? null;

                    if ($triggerId === null) {
                        continue;
                    }

                    $value = $condition['value'] ?? null;

                    // Choice-trigger values are option ids — remap when known.
                    if ($value !== null && isset($optionIdMap[(int) $value])) {
                        $value = (string) $optionIdMap[(int) $value];
                    }

                    $conditions[] = [
                        'question_id' => $triggerId,
                        'operator' => $condition['operator'] ?? 'is_answered',
                        'value' => $value,
                    ];
                }

                $question->update(['logic' => $conditions === [] ? null : [
                    'match' => $logic['match'] ?? 'all',
                    'conditions' => $conditions,
                ]]);
            }

            return $quiz;
        });
    }
}
