<?php

namespace Tests\Feature\Billing;

use App\Enums\WorkspaceRole;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Subscription;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function subscribedWorkspace(string $status = Subscription::STATUS_ACTIVE): Workspace
    {
        $owner = User::factory()->create([
            'name' => 'Ada Owner',
            'email' => $status.'-ada@example.com',
        ]);
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create(['name' => 'Acme '.$status]);

        $subscription = $workspace->subscriptions()->create([
            'type' => 'default',
            'paddle_id' => 'sub_'.$status,
            'status' => $status,
        ]);

        $subscription->items()->create([
            'product_id' => 'pro_test',
            'price_id' => 'pri_test_123',
            'status' => 'active',
            'quantity' => 1,
        ]);

        return $workspace;
    }

    public function test_super_admins_can_see_subscriptions_with_the_customer_and_plan(): void
    {
        $workspace = $this->subscribedWorkspace();

        Plan::query()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'price' => 29,
            'price_id' => 'pri_test_123',
            'quizzes' => 20,
            'responses_per_month' => 1000,
            'members' => 3,
        ]);

        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        Livewire::test(ListSubscriptions::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Subscription::all())
            ->assertSee($workspace->name)
            ->assertSee('Ada Owner')
            ->assertSee($workspace->billingContact()->email)
            // Plan name rather than the raw gateway price id.
            ->assertSee('Pro')
            ->assertDontSee('pri_test_123');
    }

    public function test_a_customer_with_several_subscriptions_appears_once(): void
    {
        $owner = User::factory()->create(['email' => 'repeat@example.com']);
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create(['name' => 'Repeat Co']);

        foreach (range(1, 4) as $n) {
            $subscription = $workspace->subscriptions()->create([
                'type' => 'default',
                'paddle_id' => 'sub_repeat_'.$n,
                'status' => Subscription::STATUS_ACTIVE,
            ]);
            $subscription->items()->create([
                'product_id' => 'pro_test',
                'price_id' => 'pri_test_123',
                'status' => 'active',
                'quantity' => 1,
            ]);
        }

        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        $latest = Subscription::where('paddle_id', 'sub_repeat_4')->first();

        Livewire::test(ListSubscriptions::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$latest])
            ->assertCanNotSeeTableRecords(Subscription::where('paddle_id', '!=', 'sub_repeat_4')->get())
            // Duplicates are collapsed, not hidden: four live subscriptions
            // means four charges, so the row says so.
            ->assertSee('4 active');
    }

    public function test_the_details_page_shows_every_subscription_and_transaction(): void
    {
        $workspace = $this->subscribedWorkspace();
        $subscription = Subscription::first();

        $workspace->transactions()->create([
            'paddle_id' => 'txn_test_1',
            'paddle_subscription_id' => $subscription->paddle_id,
            'invoice_number' => 'INV-0001',
            'status' => 'completed',
            'total' => 2900,
            'tax' => 400,
            'currency' => 'USD',
            'billed_at' => now(),
        ]);

        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        Livewire::test(ViewSubscription::class, ['record' => $subscription->getKey()])
            ->assertOk()
            ->assertSee('Ada Owner')
            ->assertSee($workspace->billingContact()->email)
            ->assertSee($workspace->name)
            ->assertSee('INV-0001')
            // Minor units are rendered as money.
            ->assertSee('29.00 USD')
            ->assertSee('4.00 USD');
    }

    public function test_an_older_subscription_still_has_a_reachable_details_page(): void
    {
        $workspace = $this->subscribedWorkspace();

        $older = Subscription::first();

        $workspace->subscriptions()->create([
            'type' => 'default',
            'paddle_id' => 'sub_newer',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        // The list collapses to the newest; the older one must still open.
        Livewire::test(ViewSubscription::class, ['record' => $older->getKey()])
            ->assertOk()
            ->assertSee($older->paddle_id);
    }

    public function test_subscriptions_can_be_filtered_by_status(): void
    {
        $this->subscribedWorkspace(Subscription::STATUS_ACTIVE);
        $this->subscribedWorkspace(Subscription::STATUS_CANCELED);

        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        Livewire::test(ListSubscriptions::class)
            ->filterTable('status', Subscription::STATUS_CANCELED)
            ->assertCanSeeTableRecords(Subscription::where('status', Subscription::STATUS_CANCELED)->get())
            ->assertCanNotSeeTableRecords(Subscription::where('status', Subscription::STATUS_ACTIVE)->get());
    }

    public function test_the_subscriptions_screen_is_read_only(): void
    {
        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        $this->assertFalse(SubscriptionResource::canCreate());
    }

    public function test_customers_cannot_reach_the_subscriptions_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/super-admin/subscriptions')->assertRedirect();
    }

    public function test_the_empty_state_shows_when_nobody_has_subscribed(): void
    {
        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        Livewire::test(ListSubscriptions::class)
            ->assertOk()
            ->assertSee('No subscriptions yet');
    }
}
