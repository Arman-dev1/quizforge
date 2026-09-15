<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\Platform\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_log_in_as_a_customer(): void
    {
        $admin = SuperAdmin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'super_admin');

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('impersonate', $user)
            ->callTableAction('impersonate', $user)
            ->assertRedirect(route('dashboard'));

        // The customer session exists…
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame($user->id, Auth::guard('web')->id());

        // …and the platform session survived alongside it.
        $this->assertTrue(Auth::guard('super_admin')->check());
        $this->assertSame($admin->id, Auth::guard('super_admin')->id());

        $this->assertSame($admin->id, session(Impersonation::SESSION_KEY));
    }

    public function test_stopping_returns_to_the_panel_and_drops_only_the_customer_session(): void
    {
        $admin = SuperAdmin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'super_admin');

        app(Impersonation::class)->start($admin, $user);

        $this->post(route('platform.impersonate.stop'))
            ->assertRedirect('/super-admin');

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertTrue(Auth::guard('super_admin')->check());
        $this->assertNull(session(Impersonation::SESSION_KEY));
    }

    public function test_the_banner_appears_while_impersonating(): void
    {
        $admin = SuperAdmin::factory()->create(['name' => 'Platform Staffer']);
        $user = User::factory()->create(['name' => 'Casey Customer']);

        $this->actingAs($admin, 'super_admin');

        app(Impersonation::class)->start($admin, $user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Casey Customer')
            ->assertSee('Platform Staffer')
            ->assertSee(__('Stop and return to platform'));
    }

    public function test_a_customer_cannot_reach_the_screen_that_starts_an_impersonation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // The panel runs on the platform guard, so a customer session never
        // gets as far as the action.
        $this->get('/super-admin/users')->assertRedirect();

        $this->assertSame($user->id, Auth::guard('web')->id());
        $this->assertFalse(Auth::guard('super_admin')->check());
    }

    public function test_starting_a_second_impersonation_replaces_the_first(): void
    {
        $admin = SuperAdmin::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($admin, 'super_admin');

        $impersonation = app(Impersonation::class);

        $impersonation->start($admin, $first);
        $impersonation->start($admin, $second);

        $this->assertSame($second->id, Auth::guard('web')->id());
        $this->assertTrue($impersonation->isActive());
        $this->assertSame($admin->id, $impersonation->impersonator()?->id);
    }

    public function test_stopping_without_an_active_session_is_harmless(): void
    {
        $this->post(route('platform.impersonate.stop'))->assertRedirect('/super-admin');

        $this->assertFalse(Auth::guard('web')->check());
    }
}
