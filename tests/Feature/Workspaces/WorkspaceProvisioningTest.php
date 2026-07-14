<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkspaceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_personal_workspace_is_created_lazily_on_first_request(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->refresh();

        $this->assertNotNull($user->current_workspace_id);
        $this->assertSame("Jane Doe's Workspace", $user->currentWorkspace->name);
        $this->assertSame(WorkspaceRole::Owner, $user->roleIn($user->currentWorkspace));
    }

    public function test_repeat_requests_do_not_create_duplicate_workspaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(1, $user->workspaces()->count());
    }

    public function test_current_workspace_falls_back_to_another_membership_when_invalid(): void
    {
        $user = User::factory()->create();
        $own = Workspace::factory()->ownedBy($user)->create();
        $other = Workspace::factory()->withMember($user, WorkspaceRole::Editor)->create();

        $user->switchToWorkspace($other);
        $other->members()->detach($user->id);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame($own->id, $user->refresh()->current_workspace_id);
    }

    public function test_users_can_create_additional_workspaces(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->ownedBy($user)->create();

        $this->actingAs($user);

        $this->get('/workspaces/create')->assertOk();

        Volt::test('workspaces.create')
            ->set('name', 'Marketing Team')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $workspace = Workspace::where('name', 'Marketing Team')->first();

        $this->assertNotNull($workspace);
        $this->assertSame(WorkspaceRole::Owner, $user->roleIn($workspace));
        $this->assertSame($workspace->id, $user->refresh()->current_workspace_id);
    }

    public function test_workspace_slugs_are_unique(): void
    {
        $this->assertSame('acme', Workspace::generateSlug('Acme'));

        Workspace::factory()->create(['slug' => 'acme']);

        $this->assertSame('acme-2', Workspace::generateSlug('Acme'));
    }
}
