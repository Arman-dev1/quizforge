<?php

namespace Database\Factories;

use App\Models\LibraryQuestion;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryQuestion>
 */
class LibraryQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'Saved question',
            'question' => [
                'type' => 'single_choice',
                'title' => 'Saved question',
                'description' => null,
                'placeholder' => null,
                'help_text' => null,
                'is_required' => false,
                'settings' => [],
                'validation' => [],
                'options' => [
                    ['label' => 'Option 1', 'is_correct' => false],
                    ['label' => 'Option 2', 'is_correct' => false],
                ],
            ],
        ];
    }
}
