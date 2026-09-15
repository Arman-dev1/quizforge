<?php

namespace App\Actions\Quizzes;

use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishQuiz
{
    /**
     * Validate the quiz content, snapshot it as an immutable version,
     * and mark the quiz published.
     */
    public function handle(Quiz $quiz, User $user): QuizVersion
    {
        $content = $this->buildContent($quiz);

        $this->validateContent($content);

        return DB::transaction(function () use ($quiz, $user, $content) {
            $version = $quiz->versions()->create([
                'version' => ((int) $quiz->versions()->max('version')) + 1,
                'content' => $content,
                'created_by' => $user->id,
            ]);

            $quiz->update([
                'status' => QuizStatus::Published,
                'published_at' => now(),
                'archived_at' => null,
            ]);

            return $version;
        });
    }

    /**
     * Snapshot pages with their visible questions and options.
     *
     * Public because the preview screen renders draft content through the
     * exact same shape the player consumes — that is what makes the preview
     * a real preview rather than a lookalike.
     */
    public function buildContent(Quiz $quiz): array
    {
        $pages = $quiz->pages()
            ->with(['questions' => fn ($query) => $query->where('is_hidden', false)->with('options')])
            ->get()
            ->map(fn (QuizPage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'description' => $page->description,
                'questions' => $page->questions->map(fn ($question) => [
                    'id' => $question->id,
                    'type' => $question->type->value,
                    'title' => $question->title,
                    'description' => $question->description,
                    'placeholder' => $question->placeholder,
                    'help_text' => $question->help_text,
                    'is_required' => $question->is_required,
                    'settings' => $question->settings ?? [],
                    'validation' => $question->validation ?? [],
                    'logic' => $question->logic,
                    'options' => $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'label' => $option->label,
                        'is_correct' => $option->is_correct,
                        // Drives category-matched result screens.
                        'category' => ($option->settings ?? [])['category'] ?? null,
                        // Image-choice thumbnail, when one has been set.
                        'image' => ($option->settings ?? [])['image'] ?? null,
                    ])->all(),
                ])->all(),
            ])
            ->all();

        return ['pages' => $this->pruneDanglingLogic($pages)];
    }

    /**
     * Drop logic conditions whose trigger question is not part of this
     * snapshot (deleted or hidden), so rules can never dangle at runtime.
     */
    protected function pruneDanglingLogic(array $pages): array
    {
        $questionIds = collect($pages)
            ->flatMap(fn (array $page) => array_column($page['questions'], 'id'))
            ->all();

        foreach ($pages as &$page) {
            foreach ($page['questions'] as &$question) {
                if (empty($question['logic']['conditions'])) {
                    $question['logic'] = null;

                    continue;
                }

                $conditions = array_values(array_filter(
                    $question['logic']['conditions'],
                    fn (array $condition) => in_array((int) ($condition['question_id'] ?? 0), $questionIds, true),
                ));

                $question['logic'] = $conditions === []
                    ? null
                    : ['match' => $question['logic']['match'] ?? 'all', 'conditions' => $conditions];
            }
        }

        return $pages;
    }

    protected function validateContent(array $content): void
    {
        $questions = collect($content['pages'])->flatMap(fn (array $page) => $page['questions']);

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'publish' => __('Add at least one visible question before publishing.'),
            ]);
        }

        if ($questions->contains(fn (array $question) => trim($question['title']) === '')) {
            throw ValidationException::withMessages([
                'publish' => __('Every visible question needs a title before publishing.'),
            ]);
        }

        $withoutOptions = $questions->first(fn (array $question) => QuestionType::from($question['type'])->hasOptions()
            && $question['options'] === []);

        if ($withoutOptions !== null) {
            throw ValidationException::withMessages([
                'publish' => __('":title" needs at least one option before publishing.', ['title' => $withoutOptions['title']]),
            ]);
        }
    }
}
