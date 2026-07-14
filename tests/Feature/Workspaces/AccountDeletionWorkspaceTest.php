<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AccountDeletionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_is_blocked_for_the_sole_owner_of_a_shared_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Workspace::factory()
            ->ownedBy($owner)
            ->withMember($member, WorkspaceRole::Editor)
            ->create();

        $this->actingAs($owner);

        Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($owner->fresh());
    }

    public function test_solo_workspaces_are_deleted_with_the_account(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->ownedBy($owner)->create();

        $this->actingAs($owner);

        Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($owner->fresh());
        $this->assertSoftDeleted($workspace);
    }

    public function test_deletion_proceeds_when_the_shared_workspace_has_another_owner(): void
    {
        $owner = User::factory()->create();
        $coOwner = User::factory()->create();
        $workspace = Workspace::factory()
            ->ownedBy($owner)
            ->withMember($coOwner, WorkspaceRole::Owner)
            ->create();

        $this->actingAs($owner);

        Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertNull($owner->fresh());
        $this->assertNull($workspace->refresh()->deleted_at);
        $this->assertTrue($workspace->hasMember($coOwner));
    }
}
