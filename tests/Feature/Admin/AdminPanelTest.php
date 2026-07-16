<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_access_the_panel_and_its_resources(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        $this->get('/admin')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/workspaces')->assertOk();
        $this->get('/admin/quiz-templates')->assertOk();
    }

    public function test_regular_users_are_denied(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user);

        $this->get('/admin')->assertForbidden();
    }

    public function test_guests_are_redirected_to_the_panel_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_the_make_admin_command_grants_and_revokes_access(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('app:make-admin', ['email' => 'boss@example.com'])
            ->assertExitCode(0);

        $this->assertTrue($user->refresh()->is_admin);

        $this->artisan('app:make-admin', ['email' => 'boss@example.com', '--revoke' => true])
            ->assertExitCode(0);

        $this->assertFalse($user->refresh()->is_admin);

        $this->artisan('app:make-admin', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    }
}
