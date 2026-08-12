<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The editable content for the public marketing/landing page.
 *
 * Everything the visitor sees lives in a single JSON `data` blob so the
 * super-admin can manage it all from one screen. `content()` deep-merges
 * the stored data over the shipped defaults, so new default fields appear
 * even before an admin edits, while list deletions still stick.
 */
class SiteSetting extends Model
{
    protected $fillable = ['data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** The one-and-only settings row, lazily created from the defaults. */
    public static function current(): self
    {
        return static::query()->firstOr(fn () => static::query()->create(['data' => static::defaults()]));
    }

    /** Stored data merged over defaults, ready for the view. */
    public function content(): array
    {
        return static::deepMerge(static::defaults(), $this->data ?? []);
    }

    /**
     * Recursively merge $override onto $base. Associative arrays merge key
     * by key (so newly-added default fields survive); list arrays (features,
     * testimonials, …) are taken wholesale from $override so removals stick.
     */
    protected static function deepMerge(array $base, array $override): array
    {
        if (array_is_list($base) || array_is_list($override)) {
            return $override === [] && $base !== [] ? $override : ($override ?: $base);
        }

        $result = $base;

        foreach ($override as $key => $value) {
            $result[$key] = is_array($value) && isset($base[$key]) && is_array($base[$key])
                ? static::deepMerge($base[$key], $value)
                : $value;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'brand' => 'QuizForge',
            'nav' => [
                ['label' => 'Features', 'url' => '#features'],
                ['label' => 'How it works', 'url' => '#how'],
                ['label' => 'Pricing', 'url' => '#pricing'],
                ['label' => 'FAQ', 'url' => '#faq'],
            ],

            'hero_badge' => 'Quizzes, surveys, assessments & forms — one builder',
            'hero_title' => 'Build quizzes people',
            'hero_highlight' => 'actually finish',
            'hero_subtitle' => 'QuizForge helps teams create beautiful quizzes, capture qualified leads, and understand every response — without wrestling with clunky form tools.',
            'hero_primary_label' => 'Start building — free',
            'hero_secondary_label' => 'See how it works',
            'hero_secondary_url' => '#how',
            'hero_note' => 'No credit card · Free forever plan · 2-minute setup',

            'logos_heading' => 'Trusted by teams at fast-growing companies',
            'logos' => [
                ['name' => 'Northwind', 'icon' => 'cube-transparent'],
                ['name' => 'Apex', 'icon' => 'play'],
                ['name' => 'Loopwork', 'icon' => 'arrow-path-rounded-square'],
                ['name' => 'Cratebox', 'icon' => 'cube'],
                ['name' => 'Lensly', 'icon' => 'camera'],
            ],

            'features_eyebrow' => 'Everything you need',
            'features_title' => 'One builder for every kind of quiz',
            'features_subtitle' => 'From lead-gen scorecards to graded assessments — QuizForge handles the whole flow, start to insight.',
            'features' => [
                ['icon' => 'squares-plus', 'title' => 'Visual builder', 'text' => '22 question types, multi-page flows, and instant autosave. No manual needed.', 'tone' => 'teal'],
                ['icon' => 'user-plus', 'title' => 'Lead capture', 'text' => 'Turn every quiz into a lead magnet — even partial responses capture contacts.', 'tone' => 'amber'],
                ['icon' => 'chart-bar', 'title' => 'Deep analytics', 'text' => 'Completion rates, drop-off points, and question performance at a glance.', 'tone' => 'teal'],
                ['icon' => 'arrows-pointing-out', 'title' => 'Logic & scoring', 'text' => 'Branching, skip logic, weighted scores, grades, and personality outcomes.', 'tone' => 'teal'],
                ['icon' => 'users', 'title' => 'Built for teams', 'text' => 'Workspaces with roles and permissions, from solo creators to whole departments.', 'tone' => 'amber'],
                ['icon' => 'bolt', 'title' => 'Fast everywhere', 'text' => 'Lightweight quiz pages that load in under a second on any device.', 'tone' => 'teal'],
            ],

            'how_eyebrow' => 'How it works',
            'how_title' => 'Launch in three steps',
            'steps' => [
                ['number' => '01', 'title' => 'Build your quiz', 'text' => 'Drag in questions, set correct answers and scoring, and organize into pages — all with live autosave.'],
                ['number' => '02', 'title' => 'Share the link', 'text' => 'Publish to a clean public URL or embed it anywhere. Every visit is tracked from the first click.'],
                ['number' => '03', 'title' => 'Read the insights', 'text' => 'Watch responses, scores, and captured leads roll in — then export or push them to your CRM.'],
            ],

            'testimonials_eyebrow' => 'Loved by creators',
            'testimonials_title' => 'Teams ship more with QuizForge',
            'testimonials' => [
                ['quote' => 'We replaced three separate tools with QuizForge. Our lead quiz now converts 40% better and setup took an afternoon.', 'name' => 'Maria Reyes', 'role' => 'Head of Growth, Loopwork', 'initials' => 'MR'],
                ['quote' => 'The analytics finally tell us where people drop off. We rewrote two questions and completion jumped to 91%.', 'name' => 'James Tan', 'role' => 'Product Lead, Apex', 'initials' => 'JT'],
                ['quote' => 'Our whole training team builds assessments now. Roles and permissions mean I don’t have to gatekeep anything.', 'name' => 'Aisha Khan', 'role' => 'L&D Manager, Northwind', 'initials' => 'AK'],
            ],

            'pricing_eyebrow' => 'Pricing',
            'pricing_title' => 'Simple plans that scale with you',
            'pricing_subtitle' => 'Start free. Upgrade when your quizzes take off.',
            'plans' => [
                [
                    'name' => 'Free', 'price' => '$0', 'period' => '/mo', 'tagline' => 'For trying things out',
                    'cta_label' => 'Get started', 'popular' => false,
                    'features' => ['3 quizzes', '100 responses / mo', '3 team members', 'Basic analytics'],
                ],
                [
                    'name' => 'Pro', 'price' => '$29', 'period' => '/mo', 'tagline' => 'For growing teams',
                    'cta_label' => 'Upgrade to Pro', 'popular' => true,
                    'features' => ['20 quizzes', '1,000 responses / mo', '10 team members', 'Logic, branching & scoring', 'Lead capture & export'],
                ],
                [
                    'name' => 'Scale', 'price' => '$79', 'period' => '/mo', 'tagline' => 'For serious volume',
                    'cta_label' => 'Choose Scale', 'popular' => false,
                    'features' => ['Unlimited quizzes', '10,000 responses / mo', 'Unlimited members', 'Advanced analytics & API', 'Priority support'],
                ],
            ],

            'faq_eyebrow' => 'FAQ',
            'faq_title' => 'Questions, answered',
            'faqs' => [
                ['question' => 'Do I need a credit card to start?', 'answer' => 'No. The Free plan is genuinely free forever — no card, no trial clock. Upgrade only when you need more quizzes or responses.'],
                ['question' => 'Can I capture leads even if someone doesn’t finish?', 'answer' => 'Yes. Partial responses are saved as soon as a contact field is filled, so you never lose a qualified lead to an abandoned quiz.'],
                ['question' => 'Does QuizForge support scoring and grades?', 'answer' => 'Absolutely — set points per answer, penalties, pass marks, and custom grade bands. Respondents can see their result instantly.'],
                ['question' => 'Can my team collaborate?', 'answer' => 'Invite members into a shared workspace with roles and permissions. Everyone edits the quizzes they’re allowed to, nothing more.'],
                ['question' => 'Can I export my responses?', 'answer' => 'Export any quiz to CSV in one click, or connect the API on the Scale plan to push responses straight into your CRM.'],
            ],

            'cta_title' => 'Build your first quiz in minutes',
            'cta_subtitle' => 'Join thousands of teams turning quizzes into leads and insight with QuizForge.',
            'cta_primary_label' => 'Start building — free',
            'cta_secondary_label' => 'View pricing',
            'cta_secondary_url' => '#pricing',

            'footer_tagline' => 'The quiz builder for teams who care about completion, leads, and insight.',
            'footer_columns' => [
                ['heading' => 'Product', 'links' => [
                    ['label' => 'Features', 'url' => '#features'],
                    ['label' => 'Pricing', 'url' => '#pricing'],
                    ['label' => 'Templates', 'url' => '#'],
                    ['label' => 'Integrations', 'url' => '#'],
                ]],
                ['heading' => 'Company', 'links' => [
                    ['label' => 'About', 'url' => '#'],
                    ['label' => 'Blog', 'url' => '#'],
                    ['label' => 'Careers', 'url' => '#'],
                    ['label' => 'Contact', 'url' => '#'],
                ]],
                ['heading' => 'Legal', 'links' => [
                    ['label' => 'Privacy', 'url' => '#'],
                    ['label' => 'Terms', 'url' => '#'],
                    ['label' => 'Security', 'url' => '#'],
                ]],
            ],
            'footer_copyright' => '© '.date('Y').' QuizForge. All rights reserved.',
        ];
    }
}
