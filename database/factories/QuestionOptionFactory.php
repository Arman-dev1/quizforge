<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'label' => fake()->words(2, true),
            'position' => 0,
        ];
    }
}
