<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A public document edited by the platform owner — About, Terms, Privacy,
 * Refunds, Integrations.
 *
 * `body` is author-written HTML from the rich text editor, so it goes
 * through HtmlSanitizer on the way in and is the only thing on the public
 * site rendered with `{!! !!}`.
 */
class ContentPage extends Model
{
    /** Slugs the application routes to and therefore must always exist. */
    public const INTEGRATIONS = 'integrations';

    public const ABOUT = 'about';

    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    public const REFUNDS = 'refunds';

    protected $fillable = [
        'slug',
        'title',
        'nav_label',
        'excerpt',
        'body',
        'is_published',
        'show_in_footer',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Terms and privacy documents run long — the sanitizer's 20k default is
     * sized for a quiz result blurb and would silently truncate a policy.
     */
    protected const MAX_BODY_LENGTH = 200000;

    protected static function booted(): void
    {
        // Sanitize wherever the body arrives from — the Filament editor, a
        // seeder, or a future import — rather than trusting each caller.
        static::saving(function (self $page) {
            if ($page->isDirty('body')) {
                $page->body = $page->body
                    ? app(HtmlSanitizer::class)->clean($page->body, self::MAX_BODY_LENGTH)
                    : null;
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeInFooter(Builder $query): Builder
    {
        return $query->published()->where('show_in_footer', true)->orderBy('position');
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }

    public function label(): string
    {
        return $this->nav_label ?: $this->title;
    }

    /** The integrations page renders provider cards as well as its body. */
    public function showsIntegrationCards(): bool
    {
        return $this->slug === self::INTEGRATIONS;
    }

    /**
     * The documents a fresh install ships with. Every buyer needs these
     * pages to exist — an empty Terms link is worse than no link — so they
     * arrive seeded with usable starter copy that says plainly it is a
     * template to replace.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function starterPages(): array
    {
        return [
            [
                'slug' => self::INTEGRATIONS,
                'title' => 'Integrations',
                'nav_label' => 'Integrations',
                'excerpt' => 'Send every lead straight into the tools your team already uses.',
                'position' => 1,
                // Already linked from the Product column and the top nav —
                // a second copy in the Company column just looks careless.
                'show_in_footer' => false,
                'body' => '<p>Connect a mailing list to any quiz, choose the audience, and map your fields. Completed responses sync automatically in the background, with retries if a provider is briefly unavailable.</p><p>Partial responses are captured too, so an abandoned quiz still gives you the email address.</p>',
            ],
            [
                'slug' => self::ABOUT,
                'title' => 'About us',
                'nav_label' => 'About',
                'excerpt' => 'Who we are and why we built this.',
                'position' => 2,
                'body' => '<p><em>Replace this text with your own story.</em></p><h3>Who we are</h3><p>Tell visitors who is behind the product, where you are based, and how long you have been doing this. A short, specific paragraph builds more trust than a long vague one.</p><h3>Why we built it</h3><p>Explain the problem you kept running into and what you decided to do about it.</p><h3>Get in touch</h3><p>Add a real email address here. People check.</p>',
            ],
            [
                'slug' => self::TERMS,
                'title' => 'Terms and conditions',
                'nav_label' => 'Terms',
                'excerpt' => 'The rules for using this service.',
                'position' => 3,
                'body' => '<p><strong>Replace this template with terms reviewed for your own business and country. It is a starting point, not legal advice.</strong></p><h3>1. Accepting these terms</h3><p>By creating an account you agree to these terms. If you do not agree, please do not use the service.</p><h3>2. Your account</h3><p>You are responsible for your account and for keeping your password secure. Tell us promptly about any unauthorised use.</p><h3>3. Acceptable use</h3><p>Do not use the service to break the law, to send unsolicited mail, or to collect data you have no right to collect.</p><h3>4. Your content</h3><p>You keep ownership of the quizzes and responses you create. You grant us the limited rights needed to host and display them.</p><h3>5. Payment</h3><p>Paid plans are billed in advance on a recurring basis. Describe your billing cycle, taxes and cancellation here.</p><h3>6. Availability</h3><p>We work to keep the service available but do not guarantee uninterrupted access.</p><h3>7. Ending your account</h3><p>You can close your account at any time. We may suspend accounts that breach these terms.</p><h3>8. Changes</h3><p>We may update these terms and will note the date of the latest revision.</p>',
            ],
            [
                'slug' => self::PRIVACY,
                'title' => 'Privacy policy',
                'nav_label' => 'Privacy',
                'excerpt' => 'What we collect, why, and what you can ask us to do about it.',
                'position' => 4,
                'body' => '<p><strong>Replace this template with a policy that matches what you actually collect. It is a starting point, not legal advice.</strong></p><h3>What we collect</h3><p>Account details you give us, quiz content you create, responses your respondents submit, and basic technical data such as IP address and browser.</p><h3>Why we collect it</h3><p>To run the service, to bill you, to keep the service secure, and to answer support questions.</p><h3>Respondent data</h3><p>When someone answers one of your quizzes, that data belongs to you. We process it on your behalf.</p><h3>Sharing</h3><p>We share data with the providers needed to run the service — hosting, payments, and any mailing lists you choose to connect. We do not sell personal data.</p><h3>Retention</h3><p>We keep data while your account is active. Say how long you keep it after closure.</p><h3>Your rights</h3><p>Depending on where you live, you may be able to request a copy of your data, correct it, or ask us to delete it. Give a contact address for those requests.</p><h3>Cookies</h3><p>We use cookies to keep you signed in and to remember preferences.</p>',
            ],
            [
                'slug' => self::REFUNDS,
                'title' => 'Refund policy',
                'nav_label' => 'Refunds',
                'excerpt' => 'When we refund, and how to ask.',
                'position' => 5,
                'body' => '<p><strong>Replace this template with the policy you intend to honour, and check it against the consumer law where you operate.</strong></p><h3>Free plan</h3><p>The free plan costs nothing, so there is nothing to refund. Try it before you upgrade.</p><h3>Paid plans</h3><p>State your refund window — for example, a full refund within 14 days of a first payment if the service has not been substantially used.</p><h3>Renewals</h3><p>Say whether renewal charges are refundable and how much notice is needed to cancel before one.</p><h3>How to request a refund</h3><p>Give a real email address and say how quickly you respond.</p><h3>Exceptions</h3><p>List anything you do not refund, such as accounts closed for breaching the terms.</p>',
            ],
        ];
    }
}
