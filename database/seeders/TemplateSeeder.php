<?php

namespace Database\Seeders;

use App\Models\QuizTemplate;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Seed the global (system) quiz templates. Idempotent: existing
     * global templates with the same name are replaced.
     */
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            QuizTemplate::updateOrCreate(
                ['workspace_id' => null, 'name' => $template['name']],
                $template,
            );
        }
    }

    protected function question(int $id, string $type, string $title, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'description' => null,
            'placeholder' => null,
            'help_text' => null,
            'is_required' => false,
            'is_hidden' => false,
            'settings' => [],
            'validation' => [],
            'logic' => null,
            'options' => [],
        ], $extra);
    }

    protected function options(int $firstId, array $labels, array $correct = []): array
    {
        $options = [];

        foreach ($labels as $index => $label) {
            $options[] = [
                'id' => $firstId + $index,
                'label' => $label,
                'is_correct' => in_array($label, $correct, true),
            ];
        }

        return $options;
    }

    protected function templates(): array
    {
        return [
            [
                'name' => 'Customer Satisfaction Survey',
                'description' => 'Measure how happy customers are with your product and support, ending with an NPS question.',
                'category' => 'Customer Feedback',
                'type' => 'customer_feedback',
                'content' => [
                    'settings' => [],
                    'pages' => [
                        [
                            'title' => 'Your experience',
                            'description' => 'This takes less than 2 minutes.',
                            'questions' => [
                                $this->question(1, 'rating', 'How satisfied are you with our product?', [
                                    'is_required' => true, 'settings' => ['max' => 5],
                                ]),
                                $this->question(2, 'rating', 'How satisfied are you with our support?', [
                                    'settings' => ['max' => 5],
                                ]),
                                $this->question(3, 'long_text', 'What could we do better?', [
                                    'placeholder' => 'Be as honest as you like…',
                                ]),
                            ],
                        ],
                        [
                            'title' => 'One last thing',
                            'description' => null,
                            'questions' => [
                                $this->question(4, 'nps', 'How likely are you to recommend us to a friend or colleague?', [
                                    'is_required' => true,
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Lead Generation Quiz',
                'description' => 'Qualify visitors with two quick questions, then capture their contact details.',
                'category' => 'Marketing',
                'type' => 'lead_generation',
                'content' => [
                    'settings' => ['collect_leads' => true],
                    'pages' => [
                        [
                            'title' => 'About your needs',
                            'description' => null,
                            'questions' => [
                                $this->question(1, 'single_choice', 'What best describes your team size?', [
                                    'is_required' => true,
                                    'options' => $this->options(101, ['Just me', '2–10 people', '11–50 people', '50+ people']),
                                ]),
                                $this->question(2, 'single_choice', 'When are you looking to get started?', [
                                    'options' => $this->options(105, ['Right away', 'Within a month', 'Just researching']),
                                ]),
                            ],
                        ],
                        [
                            'title' => 'Where should we send your results?',
                            'description' => null,
                            'questions' => [
                                $this->question(3, 'short_text', 'Your name', ['is_required' => true]),
                                $this->question(4, 'email', 'Work email', ['is_required' => true, 'placeholder' => 'you@company.com']),
                                $this->question(5, 'phone', 'Phone (optional)'),
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Employee Engagement Pulse',
                'description' => 'A short anonymous pulse check on motivation, workload, and team health.',
                'category' => 'HR',
                'type' => 'employee_survey',
                'content' => [
                    'settings' => [],
                    'pages' => [
                        [
                            'title' => 'This week',
                            'description' => 'Your answers are anonymous.',
                            'questions' => [
                                $this->question(1, 'opinion_scale', 'I feel motivated in my work.', [
                                    'is_required' => true,
                                    'settings' => ['min' => 1, 'max' => 10, 'min_label' => 'Strongly disagree', 'max_label' => 'Strongly agree'],
                                ]),
                                $this->question(2, 'opinion_scale', 'My workload is manageable.', [
                                    'is_required' => true,
                                    'settings' => ['min' => 1, 'max' => 10, 'min_label' => 'Strongly disagree', 'max_label' => 'Strongly agree'],
                                ]),
                                $this->question(3, 'yes_no', 'Do you have what you need to do your job well?'),
                                $this->question(4, 'long_text', 'Anything you want leadership to know?'),
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'General Knowledge Quiz',
                'description' => 'A scored five-question starter quiz with correct answers and a pass mark already configured.',
                'category' => 'Education',
                'type' => 'general_quiz',
                'content' => [
                    'settings' => [
                        'scored' => true,
                        'results' => [
                            'show_score' => true,
                            'pass_percentage' => 60,
                            'grades' => [
                                ['min' => 80, 'label' => 'Expert'],
                                ['min' => 60, 'label' => 'Solid'],
                                ['min' => 0, 'label' => 'Keep practicing'],
                            ],
                            'thank_you_message' => null,
                            'redirect_url' => null,
                        ],
                    ],
                    'pages' => [
                        [
                            'title' => null,
                            'description' => null,
                            'questions' => [
                                $this->question(1, 'single_choice', 'Which planet is known as the Red Planet?', [
                                    'is_required' => true, 'settings' => ['points' => 1],
                                    'options' => $this->options(101, ['Venus', 'Mars', 'Jupiter', 'Mercury'], ['Mars']),
                                ]),
                                $this->question(2, 'single_choice', 'What is the largest ocean on Earth?', [
                                    'is_required' => true, 'settings' => ['points' => 1],
                                    'options' => $this->options(105, ['Atlantic', 'Indian', 'Pacific', 'Arctic'], ['Pacific']),
                                ]),
                                $this->question(3, 'single_choice', 'Who painted the Mona Lisa?', [
                                    'is_required' => true, 'settings' => ['points' => 1],
                                    'options' => $this->options(109, ['Michelangelo', 'Leonardo da Vinci', 'Raphael', 'Rembrandt'], ['Leonardo da Vinci']),
                                ]),
                                $this->question(4, 'single_choice', 'What is the chemical symbol for gold?', [
                                    'is_required' => true, 'settings' => ['points' => 1],
                                    'options' => $this->options(113, ['Go', 'Gd', 'Au', 'Ag'], ['Au']),
                                ]),
                                $this->question(5, 'single_choice', 'Which country has the largest population?', [
                                    'is_required' => true, 'settings' => ['points' => 1],
                                    'options' => $this->options(117, ['China', 'India', 'United States', 'Indonesia'], ['India']),
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Event Registration',
                'description' => 'Collect attendee details, dietary needs, and session preferences for your next event.',
                'category' => 'Events',
                'type' => 'registration_form',
                'content' => [
                    'settings' => [],
                    'pages' => [
                        [
                            'title' => 'Register for the event',
                            'description' => null,
                            'questions' => [
                                $this->question(1, 'short_text', 'Full name', ['is_required' => true]),
                                $this->question(2, 'email', 'Email address', ['is_required' => true]),
                                $this->question(3, 'phone', 'Phone number'),
                                $this->question(4, 'dropdown', 'Which session will you attend?', [
                                    'is_required' => true,
                                    'options' => $this->options(101, ['Morning session', 'Afternoon session', 'Full day']),
                                ]),
                                $this->question(5, 'multiple_choice', 'Dietary requirements', [
                                    'options' => $this->options(104, ['Vegetarian', 'Vegan', 'Gluten-free', 'None']),
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Job Application Form',
                'description' => 'A structured application with contact details, links, and screening questions.',
                'category' => 'HR',
                'type' => 'application_form',
                'content' => [
                    'settings' => [],
                    'pages' => [
                        [
                            'title' => 'About you',
                            'description' => null,
                            'questions' => [
                                $this->question(1, 'short_text', 'Full name', ['is_required' => true]),
                                $this->question(2, 'email', 'Email address', ['is_required' => true]),
                                $this->question(3, 'phone', 'Phone number', ['is_required' => true]),
                                $this->question(4, 'website', 'Portfolio or LinkedIn URL'),
                            ],
                        ],
                        [
                            'title' => 'Your application',
                            'description' => null,
                            'questions' => [
                                $this->question(5, 'single_choice', 'How did you hear about this role?', [
                                    'options' => $this->options(101, ['Job board', 'Company website', 'Referral', 'Social media']),
                                ]),
                                $this->question(6, 'long_text', 'Why are you a great fit for this role?', [
                                    'is_required' => true,
                                ]),
                                $this->question(7, 'date', 'Earliest start date'),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
