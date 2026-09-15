<?php

namespace Tests\Feature\Install;

use App\Models\Plan;
use App\Models\Quiz;
use App\Models\QuizTemplate;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * What someone gets when they unzip this, migrate and seed.
 *
 * A broken fresh install is the worst kind of bug to ship — nobody who hits
 * it has a working system to debug it with.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function seedFresh(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeding_creates_no_customer_data(): void
    {
        $this->seedFresh();

        $this->assertSame(0, User::count(), 'no demo users should be seeded');
        $this->assertSame(0, Workspace::withoutGlobalScope('workspace')->count());
        $this->assertSame(0, Quiz::withoutGlobalScope('workspace')->count());
    }

    public function test_seeding_creates_the_plans_and_templates(): void
    {
        $this->seedFresh();

        $this->assertEqualsCanonicalizing(['free', 'pro'], Plan::pluck('key')->all());
        $this->assertGreaterThan(0, QuizTemplate::whereNull('workspace_id')->count());
    }

    public function test_seeding_creates_exactly_one_platform_admin(): void
    {
        config(['app.url' => 'https://example.test']);

        $this->seedFresh();

        $this->assertSame(1, SuperAdmin::count());

        $admin = SuperAdmin::first();

        $this->assertTrue($admin->is_active);
        $this->assertNotEmpty($admin->password);
    }

    public function test_the_admin_password_comes_from_the_environment_when_set(): void
    {
        $_ENV['SUPER_ADMIN_EMAIL'] = 'boss@example.test';
        $_ENV['SUPER_ADMIN_PASSWORD'] = 'a-very-long-chosen-password';

        try {
            $this->seedFresh();

            $admin = SuperAdmin::where('email', 'boss@example.test')->first();

            $this->assertNotNull($admin);
            $this->assertTrue(Hash::check('a-very-long-chosen-password', $admin->password));
        } finally {
            unset($_ENV['SUPER_ADMIN_EMAIL'], $_ENV['SUPER_ADMIN_PASSWORD']);
        }
    }

    public function test_a_generated_password_is_not_a_known_default(): void
    {
        $this->seedFresh();

        $admin = SuperAdmin::first();

        // A seeder that always creates "password" is a back door on every
        // install that runs it.
        foreach (['password', 'secret', '12345678', 'admin'] as $guess) {
            $this->assertFalse(Hash::check($guess, $admin->password), "seeded password must not be '{$guess}'");
        }
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seedFresh();

        $admin = SuperAdmin::first();
        $originalHash = $admin->password;
        $planCount = Plan::count();
        $templateCount = QuizTemplate::count();

        $this->seedFresh();

        $this->assertSame(1, SuperAdmin::count());
        $this->assertSame($originalHash, SuperAdmin::first()->password, 'an existing admin password must survive re-seeding');
        $this->assertSame($planCount, Plan::count());
        $this->assertSame($templateCount, QuizTemplate::count());
    }

    public function test_a_new_account_gets_its_own_workspace_on_first_request(): void
    {
        $this->seedFresh();

        $user = User::factory()->create(['name' => 'New Person']);

        $this->assertNull($user->current_workspace_id);

        // SetCurrentWorkspace creates one lazily — which is why no workspace
        // seeder is needed.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->refresh();

        $this->assertNotNull($user->current_workspace_id);
        $this->assertSame("New Person's Workspace", $user->currentWorkspace->name);
        $this->assertTrue($user->currentWorkspace->owners()->whereKey($user->id)->exists());
    }
}
