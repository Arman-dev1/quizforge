<?php

namespace Tests\Feature\Marketing;

use App\Models\ContentPage;
use App\Models\SiteSetting;
use Database\Seeders\ContentPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public documents an owner edits from the platform panel.
 */
class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentPageSeeder::class);
    }

    public function test_every_starter_page_is_reachable(): void
    {
        foreach (ContentPage::starterPages() as $starter) {
            $this->get(route('page.show', $starter['slug']))
                ->assertOk()
                ->assertSee($starter['title']);
        }
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get(route('page.show', 'no-such-page'))->assertNotFound();
    }

    public function test_an_unpublished_page_is_not_found(): void
    {
        ContentPage::where('slug', ContentPage::ABOUT)->update(['is_published' => false]);

        $this->get(route('page.show', ContentPage::ABOUT))->assertNotFound();
    }

    public function test_the_integrations_page_lists_the_real_providers(): void
    {
        $response = $this->get(route('page.show', ContentPage::INTEGRATIONS))->assertOk();

        // The cards come from the same config the app uses to connect, so the
        // public list can never claim a provider the product lacks.
        foreach (array_keys(config('integrations.providers')) as $key) {
            $response->assertSee(config("integrations.providers.{$key}.name"));
        }
    }

    public function test_other_pages_do_not_show_integration_cards(): void
    {
        $this->get(route('page.show', ContentPage::TERMS))
            ->assertOk()
            ->assertDontSee('Need another tool?');
    }

    public function test_the_body_is_sanitized_on_save(): void
    {
        $page = ContentPage::where('slug', ContentPage::ABOUT)->first();

        $page->update(['body' => '<p>Safe</p><script>alert(1)</script><a href="javascript:alert(2)">bad link</a>']);

        $this->assertStringNotContainsString('<script', $page->fresh()->body);
        $this->assertStringNotContainsString('javascript:', $page->fresh()->body);
        $this->assertStringContainsString('Safe', $page->fresh()->body);
    }

    public function test_a_long_policy_is_not_truncated(): void
    {
        // Terms and privacy documents run well past the sanitizer's default
        // 20k cap, which is sized for a short quiz result blurb.
        $long = str_repeat('<p>A reasonably long clause of policy text.</p>', 800);

        $page = ContentPage::where('slug', ContentPage::TERMS)->first();
        $page->update(['body' => $long]);

        $this->assertGreaterThan(30000, strlen($page->fresh()->body));
    }

    public function test_published_pages_appear_in_the_footer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('page.show', ContentPage::TERMS))
            ->assertSee(route('page.show', ContentPage::PRIVACY));
    }

    public function test_an_unpublished_page_leaves_the_footer(): void
    {
        ContentPage::where('slug', ContentPage::REFUNDS)->update(['is_published' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('page.show', ContentPage::REFUNDS));
    }

    public function test_the_footer_ships_no_dead_placeholder_links(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $footer = substr($html, (int) strrpos($html, '<footer'));

        $this->assertStringNotContainsString('href="#"', $footer, 'The footer still renders a placeholder link.');
    }

    public function test_the_nav_links_to_the_integrations_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/page/integrations');
    }

    public function test_seeding_twice_does_not_duplicate_or_overwrite(): void
    {
        $page = ContentPage::where('slug', ContentPage::ABOUT)->first();
        $page->update(['body' => '<p>My own words.</p>']);

        $this->seed(ContentPageSeeder::class);

        $this->assertSame(count(ContentPage::starterPages()), ContentPage::count());
        $this->assertStringContainsString('My own words.', $page->fresh()->body);
    }

    public function test_site_settings_defaults_contain_no_placeholder_links(): void
    {
        foreach (SiteSetting::defaults()['footer_columns'] as $column) {
            foreach ($column['links'] as $link) {
                $this->assertNotSame('#', trim($link['url']), "Footer default '{$link['label']}' points nowhere.");
            }
        }
    }
}
