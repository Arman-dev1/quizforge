<?php

namespace Tests\Feature\Billing;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Subscription;
use Tests\TestCase;

/**
 * The sync command is the safety net for when webhooks can't reach us —
 * local development especially, where Paddle cannot call 127.0.0.1.
 */
class SyncSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function workspaceWithCustomer(): Workspace
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();

        $workspace->customer()->create([
            'paddle_id' => 'ctm_test_1',
            'name' => 'Owner',
            'email' => 'owner@example.com',
        ]);

        return $workspace;
    }

    protected function fakePaddle(array $subscriptions): void
    {
        // Cashier::api() refuses to build a request without a key, so it has
        // to be set before the HTTP fake ever gets a look in.
        config(['cashier.api_key' => 'pdl_test_key', 'cashier.sandbox' => true]);

        Http::fake([
            '*subscriptions*' => Http::response(['data' => $subscriptions]),
        ]);
    }

    protected function subscriptionPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_test_1',
            'status' => 'active',
            'custom_data' => ['subscription_type' => 'default'],
            'items' => [[
                'price' => ['id' => 'pri_test_1', 'product_id' => 'pro_test_1'],
                'status' => 'active',
                'quantity' => 1,
            ]],
        ], $overrides);
    }

    public function test_it_creates_missing_subscriptions(): void
    {
        $workspace = $this->workspaceWithCustomer();
        $this->fakePaddle([$this->subscriptionPayload()]);

        $this->artisan('app:sync-subscriptions')->assertExitCode(0);

        $subscription = Subscription::where('paddle_id', 'sub_test_1')->first();

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame($workspace->id, $subscription->billable_id);
        $this->assertSame('pri_test_1', $subscription->items->first()->price_id);
        $this->assertTrue($workspace->refresh()->subscribed());
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->workspaceWithCustomer();
        $this->fakePaddle([$this->subscriptionPayload()]);

        $this->artisan('app:sync-subscriptions')->assertExitCode(0);
        $this->artisan('app:sync-subscriptions')->assertExitCode(0);

        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, Subscription::first()->items()->count());
    }

    public function test_it_updates_a_status_that_changed_at_paddle(): void
    {
        $this->workspaceWithCustomer();

        // Http::fake() merges stubs rather than replacing them, so a second
        // call would never win — sequence the two responses instead.
        config(['cashier.api_key' => 'pdl_test_key', 'cashier.sandbox' => true]);

        Http::fake([
            '*subscriptions*' => Http::sequence()
                ->push(['data' => [$this->subscriptionPayload()]])
                ->push(['data' => [$this->subscriptionPayload(['status' => 'canceled'])]]),
        ]);

        $this->artisan('app:sync-subscriptions');
        $this->assertSame('active', Subscription::first()->status);

        $this->artisan('app:sync-subscriptions');

        $this->assertSame('canceled', Subscription::first()->status);
        $this->assertSame(1, Subscription::count());
    }

    public function test_a_scheduled_cancellation_becomes_a_grace_period(): void
    {
        $this->workspaceWithCustomer();

        $this->fakePaddle([$this->subscriptionPayload([
            'scheduled_change' => ['action' => 'cancel', 'effective_at' => now()->addDays(20)->toIso8601String()],
        ])]);

        $this->artisan('app:sync-subscriptions');

        $subscription = Subscription::first();

        $this->assertNotNull($subscription->ends_at);
        $this->assertTrue($subscription->onGracePeriod());
    }

    public function test_it_can_target_a_single_workspace(): void
    {
        $this->workspaceWithCustomer();
        $this->fakePaddle([$this->subscriptionPayload()]);

        $this->artisan('app:sync-subscriptions', ['--workspace' => 99999])->assertExitCode(0);

        $this->assertSame(0, Subscription::count());
    }

    public function test_it_says_so_when_nothing_has_a_customer(): void
    {
        Workspace::factory()->create();

        $this->artisan('app:sync-subscriptions')
            ->expectsOutputToContain('No workspaces have a Paddle customer yet')
            ->assertExitCode(0);
    }
}
