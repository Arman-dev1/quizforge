<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizPage>
 */
class QuizPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'title' => fake()->sentence(3),
            'position' => 0,
        ];
    }
}
