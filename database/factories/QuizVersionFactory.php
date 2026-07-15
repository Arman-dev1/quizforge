<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizVersion>
 */
class QuizVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'version' => fn (array $attributes) => ((int) QuizVersion::where('quiz_id', $attributes['quiz_id'])->max('version')) + 1,
            'content' => ['pages' => []],
        ];
    }
}
