<?php

namespace Database\Factories;

use App\Enums\QuizType;
use App\Models\QuizTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizTemplate>
 */
class QuizTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'category' => 'General',
            'type' => QuizType::Survey,
            'content' => [
                'settings' => [],
                'pages' => [
                    [
                        'title' => 'Page 1',
                        'description' => null,
                        'questions' => [
                            [
                                'id' => 1,
                                'type' => 'short_text',
                                'title' => 'What is your name?',
                                'description' => null,
                                'placeholder' => null,
                                'help_text' => null,
                                'is_required' => false,
                                'is_hidden' => false,
                                'settings' => [],
                                'validation' => [],
                                'logic' => null,
                                'options' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
