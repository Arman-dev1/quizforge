<?php

namespace Tests\Feature\Billing;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function memberWithRole(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        return $user;
    }

    public function test_owners_see_the_billing_page_with_plans_and_usage(): void
    {
        $owner = $this->memberWithRole(WorkspaceRole::Owner);

        $this->actingAs($owner)
            ->get(route('settings.billing'))
            ->assertOk()
            ->assertSee(__('Current plan: :plan', ['plan' => 'Free']))
            ->assertSee(__('Responses this month'))
            ->assertSee('Pro')
            ->assertSee('Scale');
    }

    public function test_non_owners_cannot_open_the_billing_page(): void
    {
        $admin = $this->memberWithRole(WorkspaceRole::Admin);

        $this->actingAs($admin)
            ->get(route('settings.billing'))
            ->assertForbidden();
    }

    public function test_the_unconfigured_notice_shows_without_paddle_keys(): void
    {
        $owner = $this->memberWithRole(WorkspaceRole::Owner);

        $this->actingAs($owner)
            ->get(route('settings.billing'))
            ->assertOk()
            ->assertSee('Checkout is not configured yet')
            ->assertSee(__('Checkout unavailable'));
    }
}
