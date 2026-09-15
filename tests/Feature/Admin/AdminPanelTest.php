<?php

namespace Tests\Feature\Admin;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admins_can_access_the_panel_and_its_resources(): void
    {
        $admin = SuperAdmin::factory()->create();

        $this->actingAs($admin, 'super_admin');

        $this->get('/super-admin')->assertOk();
        $this->get('/super-admin/users')->assertOk();
        $this->get('/super-admin/workspaces')->assertOk();
        $this->get('/super-admin/quiz-templates')->assertOk();
        $this->get('/super-admin/super-admins')->assertOk();
    }

    public function test_deactivated_super_admins_are_denied(): void
    {
        $admin = SuperAdmin::factory()->inactive()->create();

        $this->actingAs($admin, 'super_admin');

        $this->get('/super-admin')->assertForbidden();
    }

    public function test_customer_accounts_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create();

        // Signed in on the customer guard only — the panel must not see them.
        $this->actingAs($user);

        $this->get('/super-admin')->assertRedirect();
    }

    public function test_guests_are_redirected_to_the_panel_login(): void
    {
        $this->get('/super-admin')->assertRedirect();
    }

    public function test_the_old_admin_path_is_gone(): void
    {
        $admin = SuperAdmin::factory()->create();

        $this->actingAs($admin, 'super_admin');

        $this->get('/admin')->assertNotFound();
    }

    public function test_the_make_super_admin_command_creates_updates_and_revokes(): void
    {
        $this->artisan('app:make-super-admin', [
            'email' => 'boss@example.com',
            '--name' => 'Boss Person',
            '--password' => 'a-long-enough-password',
        ])->assertExitCode(0);

        $admin = SuperAdmin::where('email', 'boss@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Boss Person', $admin->name);
        $this->assertTrue($admin->is_active);

        // Re-running updates rather than duplicating.
        $this->artisan('app:make-super-admin', [
            'email' => 'boss@example.com',
            '--name' => 'Renamed Boss',
        ])->assertExitCode(0);

        $this->assertSame(1, SuperAdmin::where('email', 'boss@example.com')->count());
        $this->assertSame('Renamed Boss', $admin->refresh()->name);

        $this->artisan('app:make-super-admin', ['email' => 'boss@example.com', '--revoke' => true])
            ->assertExitCode(0);

        $this->assertFalse($admin->refresh()->is_active);

        $this->artisan('app:make-super-admin', ['email' => 'nobody@example.com', '--revoke' => true])
            ->assertExitCode(1);
    }

    public function test_short_passwords_are_rejected(): void
    {
        $this->artisan('app:make-super-admin', [
            'email' => 'weak@example.com',
            '--name' => 'Weak',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertNull(SuperAdmin::where('email', 'weak@example.com')->first());
    }
}
