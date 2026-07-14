<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkspaceSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_switch_between_their_workspaces(): void
    {
        $user = User::factory()->create();
        $first = Workspace::factory()->ownedBy($user)->create();
        $second = Workspace::factory()->withMember($user, WorkspaceRole::Viewer)->create();

        $user->switchToWorkspace($first);

        $this->actingAs($user);

        Volt::test('workspace-switcher')
            ->call('switch', $second->id)
            ->assertRedirect(route('dashboard'));

        $this->assertSame($second->id, $user->refresh()->current_workspace_id);
    }

    public function test_users_cannot_switch_to_a_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->ownedBy($user)->create();
        $foreign = Workspace::factory()->create();

        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Volt::test('workspace-switcher')->call('switch', $foreign->id);
    }
}
