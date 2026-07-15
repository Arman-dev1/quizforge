<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\QuizVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizResponse>
 */
class QuizResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'workspace_id' => fn (array $attributes) => Quiz::withoutGlobalScope('workspace')
                ->findOrFail($attributes['quiz_id'])->workspace_id,
            'quiz_version_id' => fn (array $attributes) => QuizVersion::factory()
                ->create(['quiz_id' => $attributes['quiz_id']])->id,
            'status' => QuizResponse::STATUS_IN_PROGRESS,
            'respondent_token' => Str::random(40),
            'started_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => QuizResponse::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
