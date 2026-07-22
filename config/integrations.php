<?php

use App\Services\Integrations\Drivers\ActiveCampaignDriver;
use App\Services\Integrations\Drivers\BrevoDriver;
use App\Services\Integrations\Drivers\ConvertKitDriver;
use App\Services\Integrations\Drivers\MailchimpDriver;
use App\Services\Integrations\Drivers\MailerLiteDriver;

/*
|--------------------------------------------------------------------------
| Integration catalog
|--------------------------------------------------------------------------
| The available third-party services quizzes can connect to. This is the
| first category (Email Marketing); more categories slot in under
| `categories` + `providers` later without touching the UI.
|
| Logos are rendered as branded monogram tiles (initial on the service's
| brand colour). Drop real logo assets in later by adding a `logo` path.
*/

return [
    'categories' => [
        'email_marketing' => [
            'label' => 'Email Marketing',
            'description' => 'Sync quiz leads straight into your email lists and automations.',
        ],
    ],

    'providers' => [
        'mailchimp' => [
            'name' => 'Mailchimp',
            'category' => 'email_marketing',
            'description' => 'Add new quiz leads to your Mailchimp audiences automatically.',
            'color' => '#FFE01B',
            'ink' => '#241C15',
            'initial' => 'M',
            'popular' => true,
            'driver' => MailchimpDriver::class,
            'resource_label' => 'Audience',
            'fields' => [
                'api_key' => 'API key',
            ],
        ],

        'brevo' => [
            'name' => 'Brevo',
            'category' => 'email_marketing',
            'description' => 'Send leads into Brevo (formerly Sendinblue) contact lists.',
            'color' => '#0B996E',
            'ink' => '#FFFFFF',
            'initial' => 'B',
            'popular' => true,
            'driver' => BrevoDriver::class,
            'resource_label' => 'Contact list',
            'fields' => [
                'api_key' => 'API key',
            ],
        ],

        'mailerlite' => [
            'name' => 'MailerLite',
            'category' => 'email_marketing',
            'description' => 'Grow MailerLite subscriber groups from every quiz response.',
            'color' => '#09C269',
            'ink' => '#FFFFFF',
            'initial' => 'M',
            'popular' => false,
            'driver' => MailerLiteDriver::class,
            'resource_label' => 'Group',
            'fields' => [
                'api_key' => 'API key',
            ],
        ],

        'activecampaign' => [
            'name' => 'ActiveCampaign',
            'category' => 'email_marketing',
            'description' => 'Push leads into ActiveCampaign and trigger automations.',
            'color' => '#356AE6',
            'ink' => '#FFFFFF',
            'initial' => 'A',
            'popular' => true,
            'driver' => ActiveCampaignDriver::class,
            'resource_label' => 'List',
            'fields' => [
                'api_url' => 'API URL',
                'api_key' => 'API key',
            ],
        ],

        'convertkit' => [
            'name' => 'ConvertKit',
            'category' => 'email_marketing',
            'description' => 'Subscribe quiz leads to your ConvertKit (Kit) forms and sequences.',
            'color' => '#FB6970',
            'ink' => '#FFFFFF',
            'initial' => 'C',
            'popular' => false,
            'driver' => ConvertKitDriver::class,
            'resource_label' => 'Form',
            'fields' => [
                'api_key' => 'API key',
            ],
        ],
    ],
];
