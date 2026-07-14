<?php

namespace Database\Factories;

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->catchPhrase();

        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => fake()->randomElement(QuizType::cases()),
            'status' => QuizStatus::Draft,
            'settings' => QuizType::GeneralQuiz->defaultSettings(),
        ];
    }

    public function archived(): static
    {
        return $this->state([
            'status' => QuizStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function inWorkspace(Workspace $workspace): static
    {
        return $this->state(['workspace_id' => $workspace->id]);
    }
}
