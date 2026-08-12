<?php

namespace Tests\Feature\Landing;

use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_renders_the_default_content(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('QuizForge')
            ->assertSee('Build quizzes people')
            ->assertSee('Visual builder')
            ->assertSee('MOST POPULAR')                        // pricing highlight badge
            ->assertSee('Do I need a credit card to start?');
    }

    public function test_the_landing_page_reflects_edited_settings(): void
    {
        $setting = SiteSetting::current();
        $data = $setting->data;
        $data['hero_title'] = 'A completely different headline';
        $data['features'] = [
            ['icon' => 'bolt', 'title' => 'Custom feature name', 'text' => 'Edited from admin.', 'tone' => 'teal'],
        ];
        $setting->update(['data' => $data]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('A completely different headline')
            ->assertSee('Custom feature name')
            ->assertDontSee('Visual builder');
    }

    public function test_content_deep_merges_stored_data_over_defaults(): void
    {
        $setting = SiteSetting::query()->create(['data' => ['hero_title' => 'Only this changed']]);

        $content = $setting->content();

        // Overridden scalar wins…
        $this->assertSame('Only this changed', $content['hero_title']);
        // …while untouched keys fall back to the shipped defaults.
        $this->assertSame(SiteSetting::defaults()['brand'], $content['brand']);
        $this->assertNotEmpty($content['faqs']);
    }

    public function test_only_one_settings_row_is_ever_created(): void
    {
        SiteSetting::current();
        SiteSetting::current();

        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_super_admins_can_manage_the_landing_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(ManageSiteSettings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([SiteSetting::current()]);
    }

    public function test_non_admins_cannot_reach_the_admin_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/site-settings')
            ->assertForbidden();
    }
}
