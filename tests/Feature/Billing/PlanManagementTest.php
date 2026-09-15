<?php

namespace Tests\Feature\Billing;

use App\Enums\WorkspaceRole;
use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\UsageLimits;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function freeWorkspace(): Workspace
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        return $workspace;
    }

    protected function makeFreePlan(array $overrides = []): Plan
    {
        return Plan::query()->create(array_merge([
            'key' => 'free', 'name' => 'Free', 'price' => 0, 'quizzes' => 5,
            'responses_per_month' => 200, 'members' => 2,
            'integrations' => false, 'custom_code' => false,
            'features' => [], 'is_popular' => false, 'position' => 0, 'is_active' => true,
        ], $overrides));
    }

    public function test_managed_plans_override_the_config_defaults(): void
    {
        $this->makeFreePlan();
        $workspace = $this->freeWorkspace();
        $limits = app(UsageLimits::class);

        // DB value (5) wins over the config default (3).
        $this->assertSame(5, $limits->limit($workspace, 'quizzes'));
        $this->assertSame(2, $limits->limit($workspace, 'members'));
        $this->assertFalse($limits->feature($workspace, 'integrations'));
    }

    public function test_toggling_a_managed_plan_flag_unlocks_the_paid_feature(): void
    {
        $this->makeFreePlan(['integrations' => true]);
        $workspace = $this->freeWorkspace();

        $this->assertTrue(app(UsageLimits::class)->feature($workspace, 'integrations'));
    }

    public function test_seeder_creates_exactly_the_two_configured_plans(): void
    {
        app(PlanSeeder::class)->run();

        $this->assertSame(['free', 'pro'], Plan::active()->pluck('key')->all());
        $this->assertSame(1, Plan::query()->where('key', 'free')->value('members'));   // no extra seats
        $this->assertSame(3, Plan::query()->where('key', 'pro')->value('members'));    // three seats
        $this->assertTrue((bool) Plan::query()->where('key', 'pro')->value('integrations'));
    }

    public function test_super_admins_can_manage_plans(): void
    {
        $admin = SuperAdmin::factory()->create();
        $plan = $this->makeFreePlan();

        Livewire::actingAs($admin, 'super_admin')
            ->test(ManagePlans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$plan]);
    }
}
