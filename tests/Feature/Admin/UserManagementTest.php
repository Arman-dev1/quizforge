<?php

namespace Tests\Feature\Admin;

use App\Enums\WorkspaceRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function asStaff(): void
    {
        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');
    }

    public function test_users_cannot_be_created_or_edited_from_the_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($user));

        // The edit route no longer exists at all.
        $this->assertArrayNotHasKey('edit', UserResource::getPages());
    }

    public function test_the_listing_offers_view_delete_and_impersonate(): void
    {
        $user = User::factory()->create();

        $this->asStaff();

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertTableActionVisible('view', $user)
            ->assertTableActionVisible('delete', $user)
            ->assertTableActionVisible('impersonate', $user);
    }

    public function test_a_user_can_be_deleted_from_the_listing(): void
    {
        $user = User::factory()->create();

        $this->asStaff();

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $user)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_the_details_page_shows_the_account_and_its_workspaces(): void
    {
        $user = User::factory()->create(['name' => 'Casey Customer', 'email' => 'casey@example.com']);
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create(['name' => 'Acme Co']);
        $user->switchToWorkspace($workspace);

        $this->asStaff();

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->assertOk()
            ->assertSee('Casey Customer')
            ->assertSee('casey@example.com')
            ->assertSee('Acme Co')
            ->assertSee('Owner');
    }

    public function test_deleting_a_sole_owner_warns_about_the_stranded_workspace(): void
    {
        $owner = User::factory()->create();
        Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create(['name' => 'Orphan Co']);

        $warning = UserResource::deletionWarning($owner);

        $this->assertStringContainsString('only owner', $warning);
        $this->assertStringContainsString('Orphan Co', $warning);
    }

    public function test_no_warning_when_the_workspace_has_another_owner(): void
    {
        $owner = User::factory()->create();
        $second = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $workspace->members()->attach($second->id, ['role' => WorkspaceRole::Owner->value]);

        $this->assertStringNotContainsString('only owner', UserResource::deletionWarning($owner));
    }

    public function test_customers_cannot_reach_the_user_details_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/super-admin/users/'.$user->id)->assertRedirect();
    }
}
