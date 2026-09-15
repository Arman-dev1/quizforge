<?php

/*
|--------------------------------------------------------------------------
| Subscription plans (defaults)
|--------------------------------------------------------------------------
| These are the SHIPPED DEFAULTS. In production the super-admin manages the
| live plans in the database (the `plans` table / Filament "Plans" screen),
| and UsageLimits reads those when present — falling back to this config
| whenever no plan rows exist (e.g. a fresh install or the test suite).
|
| A `null` limit means unlimited. `flags` are the paid/free feature gates.
| Paddle price ids come from the environment; while they are empty every
| workspace resolves to the free plan and checkout renders "not configured".
*/

return [
    'free' => [
        'name' => 'Free',
        'price' => 0,
        'price_id' => null,
        'tagline' => 'For trying things out',
        'popular' => false,
        'limits' => [
            'quizzes' => 3,
            'responses_per_month' => 100,
            'members' => 1,
        ],
        'flags' => [
            'integrations' => false,
            'custom_code' => false,
            'remove_branding' => false,
        ],
        'features' => [
            '3 quizzes',
            '100 responses / mo',
            'Just you — no extra seats',
            'Basic analytics',
        ],
    ],

    'pro' => [
        'name' => 'Pro',
        'price' => 29,
        'price_id' => env('PADDLE_PRICE_PRO'),
        'tagline' => 'For growing teams',
        'popular' => true,
        'limits' => [
            'quizzes' => 20,
            'responses_per_month' => 1000,
            'members' => 3,
        ],
        'flags' => [
            'integrations' => true,
            'custom_code' => true,
            'remove_branding' => true,
        ],
        'features' => [
            '20 quizzes',
            '1,000 responses / mo',
            '3 team members',
            'Logic, branching & scoring',
            'Integrations & lead export',
            'Custom CSS & JavaScript',
            'Remove QuizForge branding',
        ],
    ],
];
