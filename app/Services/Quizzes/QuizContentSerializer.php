<?php

namespace App\Services\Quizzes;

use App\Models\Quiz;
use App\Models\QuizPage;

/**
 * Serializes a quiz's full content (including hidden questions and
 * logic) for templates. Real database ids are kept — they act as
 * template-local keys that instantiation remaps to fresh ids.
 */
class QuizContentSerializer
{
    public function toArray(Quiz $quiz): array
    {
        $pages = $quiz->pages()
            ->with('questions.options')
            ->get()
            ->map(fn (QuizPage $page) => [
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
                    'is_hidden' => $question->is_hidden,
                    'settings' => $question->settings ?? [],
                    'validation' => $question->validation ?? [],
                    'logic' => $question->logic,
                    'options' => $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'label' => $option->label,
                        'is_correct' => $option->is_correct,
                    ])->all(),
                ])->all(),
            ])
            ->all();

        return [
            'settings' => $quiz->settings ?? [],
            'pages' => $pages,
        ];
    }
}
