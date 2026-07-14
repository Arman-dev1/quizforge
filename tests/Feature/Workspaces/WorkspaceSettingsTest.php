<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function memberInWorkspace(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        return [$user, $workspace];
    }

    public function test_workspace_settings_page_is_displayed(): void
    {
        [$user] = $this->memberInWorkspace(WorkspaceRole::Viewer);

        $this->actingAs($user)->get('/settings/workspace')->assertOk();
    }

    public function test_admins_can_rename_the_workspace(): void
    {
        [$user, $workspace] = $this->memberInWorkspace(WorkspaceRole::Admin);

        $this->actingAs($user);

        Volt::test('settings.workspace')
            ->set('name', 'Renamed Workspace')
            ->call('updateName')
            ->assertHasNoErrors();

        $this->assertSame('Renamed Workspace', $workspace->refresh()->name);
    }

    public function test_editors_cannot_rename_the_workspace(): void
    {
        [$user, $workspace] = $this->memberInWorkspace(WorkspaceRole::Editor);

        $this->actingAs($user);

        Volt::test('settings.workspace')
            ->set('name', 'Hacked Name')
            ->call('updateName')
            ->assertForbidden();

        $this->assertNotSame('Hacked Name', $workspace->refresh()->name);
    }

    public function test_owners_can_delete_the_workspace_with_name_confirmation(): void
    {
        [$user, $workspace] = $this->memberInWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user);

        Volt::test('settings.workspace')
            ->set('deleteConfirmation', $workspace->name)
            ->call('deleteWorkspace')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertSoftDeleted($workspace);
        $this->assertNull($user->refresh()->current_workspace_id);
    }

    public function test_deletion_fails_when_confirmation_does_not_match(): void
    {
        [$user, $workspace] = $this->memberInWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user);

        Volt::test('settings.workspace')
            ->set('deleteConfirmation', 'wrong name')
            ->call('deleteWorkspace')
            ->assertHasErrors(['deleteConfirmation']);

        $this->assertNull($workspace->refresh()->deleted_at);
    }

    public function test_admins_cannot_delete_the_workspace(): void
    {
        [$user, $workspace] = $this->memberInWorkspace(WorkspaceRole::Admin);

        $this->actingAs($user);

        Volt::test('settings.workspace')
            ->set('deleteConfirmation', $workspace->name)
            ->call('deleteWorkspace')
            ->assertForbidden();

        $this->assertNull($workspace->refresh()->deleted_at);
    }

    public function test_members_can_leave_a_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->ownedBy($owner)
            ->withMember($member, WorkspaceRole::Editor)
            ->create();
        $member->switchToWorkspace($workspace);

        $this->actingAs($member);

        Volt::test('settings.workspace')
            ->call('leave')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($workspace->hasMember($member->refresh()));
    }

    public function test_the_sole_owner_cannot_leave_while_other_members_remain(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->ownedBy($owner)
            ->withMember($member, WorkspaceRole::Editor)
            ->create();
        $owner->switchToWorkspace($workspace);

        $this->actingAs($owner);

        Volt::test('settings.workspace')
            ->call('leave')
            ->assertHasErrors(['leave']);

        $this->assertTrue($workspace->hasMember($owner));
    }
}
