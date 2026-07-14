<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuizPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => function (array $attributes) {
                return QuizPage::find($attributes['quiz_page_id'])->quiz_id;
            },
            'quiz_page_id' => QuizPageFactory::new(),
            'type' => QuestionType::ShortText,
            'title' => fake()->sentence().'?',
            'position' => 0,
            'settings' => [],
        ];
    }

    public function ofType(QuestionType $type): static
    {
        return $this->state([
            'type' => $type,
            'settings' => $type->defaultSettings(),
        ]);
    }

    public function onPage(QuizPage $page): static
    {
        return $this->state([
            'quiz_page_id' => $page->id,
            'quiz_id' => $page->quiz_id,
        ]);
    }
}
