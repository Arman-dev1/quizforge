<?php

namespace Tests\Feature\Admin;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A super admin and a customer must be able to hold sessions at the same
 * time in the same browser — that is the whole point of the separate guard.
 */
class DualSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_into_the_panel_does_not_sign_out_the_customer(): void
    {
        $user = User::factory()->create();
        $admin = SuperAdmin::factory()->create();

        Auth::guard('web')->login($user);
        Auth::guard('super_admin')->login($admin);

        $this->assertTrue(Auth::guard('web')->check(), 'customer session was dropped');
        $this->assertTrue(Auth::guard('super_admin')->check(), 'platform session was dropped');
        $this->assertSame($user->id, Auth::guard('web')->id());
        $this->assertSame($admin->id, Auth::guard('super_admin')->id());
    }

    public function test_signing_out_of_the_panel_leaves_the_customer_signed_in(): void
    {
        $user = User::factory()->create();
        $admin = SuperAdmin::factory()->create();

        Auth::guard('web')->login($user);
        Auth::guard('super_admin')->login($admin);
        Auth::guard('super_admin')->logout();

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('super_admin')->check());
    }

    public function test_the_client_app_always_resolves_the_customer_not_the_admin(): void
    {
        $user = User::factory()->create(['name' => 'Casey Customer']);
        $admin = SuperAdmin::factory()->create(['name' => 'Platform Staffer']);

        // Default guard deliberately pointed at the panel, as it would be
        // mid-request inside Filament.
        Auth::shouldUse('super_admin');
        Auth::guard('web')->login($user);
        Auth::guard('super_admin')->login($admin);

        // SetCurrentWorkspace pins the web group back to the customer guard.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Casey Customer')
            ->assertDontSee('Platform Staffer');
    }
}
