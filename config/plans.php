<?php

/*
|--------------------------------------------------------------------------
| Subscription plans
|--------------------------------------------------------------------------
| Provider-agnostic plan definitions. A `null` limit means unlimited.
| Paddle price ids come from the environment; while they are empty the
| billing page renders checkout as "not configured" and every workspace
| resolves to the free plan.
*/

return [
    'free' => [
        'name' => 'Free',
        'price' => 0,
        'price_id' => null,
        'tagline' => 'For trying things out',
        'limits' => [
            'quizzes' => 3,
            'responses_per_month' => 100,
            'members' => 3,
        ],
    ],

    'pro' => [
        'name' => 'Pro',
        'price' => 29,
        'price_id' => env('PADDLE_PRICE_PRO'),
        'tagline' => 'For growing teams',
        'limits' => [
            'quizzes' => 20,
            'responses_per_month' => 1000,
            'members' => 10,
        ],
    ],

    'scale' => [
        'name' => 'Scale',
        'price' => 79,
        'price_id' => env('PADDLE_PRICE_SCALE'),
        'tagline' => 'For serious volume',
        'limits' => [
            'quizzes' => null,
            'responses_per_month' => 10000,
            'members' => null,
        ],
    ],
];
