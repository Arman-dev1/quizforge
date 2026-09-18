<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Models\ContentPage;
use App\Models\SuperAdmin;
use App\Models\User;
use Database\Seeders\ContentPageSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editing the public documents from the platform panel.
 *
 * The list page rendering is not enough on its own — a broken form component
 * only throws when the edit modal is opened, which is how a bad namespace
 * shipped here once before. These open the form and save through it.
 */
class ContentPagePanelTest extends TestCase
{
    use RefreshDatabase;

    protected SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentPageSeeder::class);

        $this->admin = SuperAdmin::factory()->create();
        $this->actingAs($this->admin, 'super_admin');
    }

    public function test_the_pages_list_renders(): void
    {
        $this->get('/super-admin/content-pages')->assertOk();
    }

    public function test_the_list_shows_every_document(): void
    {
        Livewire::test(ContentPageResource::getPages()['index']->getPage())
            ->assertCanSeeTableRecords(ContentPage::orderBy('position')->get());
    }

    public function test_the_edit_form_opens_and_saves(): void
    {
        $page = ContentPage::where('slug', ContentPage::ABOUT)->first();

        Livewire::test(ContentPageResource::getPages()['index']->getPage())
            ->mountAction(TestAction::make('edit')->table($page))
            ->assertActionDataSet(['title' => $page->title])
            ->setActionData([
                'title' => 'About our company',
                'body' => '<p>We started in a spare room.</p>',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $page->refresh();

        $this->assertSame('About our company', $page->title);
        $this->assertStringContainsString('spare room', $page->body);
    }

    public function test_the_editor_output_is_sanitized_on_the_way_in(): void
    {
        $page = ContentPage::where('slug', ContentPage::PRIVACY)->first();

        Livewire::test(ContentPageResource::getPages()['index']->getPage())
            ->mountAction(TestAction::make('edit')->table($page))
            ->setActionData(['body' => '<p>Fine</p><script>alert(1)</script>'])
            ->callMountedAction();

        $this->assertStringNotContainsString('<script', $page->fresh()->body);
    }

    public function test_pages_cannot_be_created_or_deleted(): void
    {
        // Each slug is routed and linked; creating or deleting here would make
        // a page with no URL, or a dead link in the footer.
        $this->assertFalse(ContentPageResource::canCreate());
        $this->assertFalse(ContentPageResource::canDelete(ContentPage::first()));
    }

    public function test_customer_accounts_cannot_reach_the_pages_screen(): void
    {
        auth('super_admin')->logout();

        $this->actingAs(User::factory()->create());

        // A signed-in customer is refused outright rather than bounced to the
        // staff login, which would imply the page exists for them.
        $this->get('/super-admin/content-pages')->assertForbidden();
    }
}
