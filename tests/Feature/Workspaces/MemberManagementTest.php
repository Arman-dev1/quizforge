<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_page_is_displayed_to_members(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Viewer)->create();
        $user->switchToWorkspace($workspace);

        $this->actingAs($user)->get('/settings/members')->assertOk();
    }

    public function test_admins_can_change_member_roles(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->withMember($admin, WorkspaceRole::Admin)
            ->withMember($member, WorkspaceRole::Viewer)
            ->create();
        $admin->switchToWorkspace($workspace);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('updateRole', $member->id, 'editor')
            ->assertHasNoErrors();

        $this->assertSame(WorkspaceRole::Editor, $member->roleIn($workspace));
    }

    public function test_admins_cannot_grant_the_owner_role(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->withMember($admin, WorkspaceRole::Admin)
            ->withMember($member, WorkspaceRole::Editor)
            ->create();
        $admin->switchToWorkspace($workspace);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('updateRole', $member->id, 'owner')
            ->assertHasErrors(['members']);

        $this->assertSame(WorkspaceRole::Editor, $member->roleIn($workspace));
    }

    public function test_owners_can_grant_the_owner_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->ownedBy($owner)
            ->withMember($member, WorkspaceRole::Admin)
            ->create();
        $owner->switchToWorkspace($workspace);

        $this->actingAs($owner);

        Volt::test('settings.members')
            ->call('updateRole', $member->id, 'owner')
            ->assertHasNoErrors();

        $this->assertSame(WorkspaceRole::Owner, $member->roleIn($workspace));
    }

    public function test_the_last_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = Workspace::factory()
            ->ownedBy($owner)
            ->withMember($other, WorkspaceRole::Admin)
            ->create();
        $owner->switchToWorkspace($workspace);

        $this->actingAs($owner);

        Volt::test('settings.members')
            ->call('updateRole', $owner->id, 'editor')
            ->assertHasErrors(['members']);

        $this->assertSame(WorkspaceRole::Owner, $owner->roleIn($workspace));
    }

    public function test_admins_can_remove_members(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->withMember($admin, WorkspaceRole::Admin)
            ->withMember($member, WorkspaceRole::Editor)
            ->create();
        $admin->switchToWorkspace($workspace);
        $member->switchToWorkspace($workspace);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('removeMember', $member->id)
            ->assertHasNoErrors();

        $this->assertFalse($workspace->hasMember($member));
        $this->assertNull($member->refresh()->current_workspace_id);
    }

    public function test_admins_cannot_remove_owners(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()
            ->ownedBy($owner)
            ->withMember($admin, WorkspaceRole::Admin)
            ->create();
        $admin->switchToWorkspace($workspace);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('removeMember', $owner->id)
            ->assertHasErrors(['members']);

        $this->assertTrue($workspace->hasMember($owner));
    }

    public function test_members_cannot_remove_themselves(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->withMember($admin, WorkspaceRole::Admin)->create();
        $admin->switchToWorkspace($workspace);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('removeMember', $admin->id)
            ->assertHasErrors(['members']);

        $this->assertTrue($workspace->hasMember($admin));
    }

    public function test_editors_cannot_manage_members(): void
    {
        $editor = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()
            ->withMember($editor, WorkspaceRole::Editor)
            ->withMember($member, WorkspaceRole::Viewer)
            ->create();
        $editor->switchToWorkspace($workspace);

        $this->actingAs($editor);

        Volt::test('settings.members')
            ->call('removeMember', $member->id)
            ->assertForbidden();

        $this->assertTrue($workspace->hasMember($member));
    }
}
