<?php

namespace Tests\Feature\Billing;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Subscription;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * What happens between "payment succeeded" in the Paddle overlay and the
 * webhook that actually records the subscription.
 *
 * Without this gap being handled the page renders the OLD plan and the
 * customer assumes their payment failed — which is exactly what they report.
 */
class CheckoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create();
        $user->switchToWorkspace($workspace);

        $workspace->customer()->create([
            'paddle_id' => 'ctm_test_1',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return [$user, $workspace];
    }

    protected function giveWorkspaceASubscription(Workspace $workspace): void
    {
        $subscription = $workspace->subscriptions()->create([
            'type' => 'default',
            'paddle_id' => 'sub_test_1',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->items()->create([
            'product_id' => 'pro_test',
            'price_id' => 'pri_test_1',
            'status' => 'active',
            'quantity' => 1,
        ]);
    }

    public function test_completing_checkout_starts_the_confirming_state(): void
    {
        [$owner] = $this->owner();

        $this->actingAs($owner);

        Volt::test('settings.billing')
            ->assertSet('confirming', false)
            ->call('paymentCompleted')
            ->assertSet('confirming', true)
            ->assertSee(__('Payment received — activating your plan…'));
    }

    public function test_it_stops_waiting_once_the_subscription_arrives(): void
    {
        [$owner, $workspace] = $this->owner();

        $this->actingAs($owner);

        $component = Volt::test('settings.billing')->call('paymentCompleted');

        // Webhook lands between polls.
        $this->giveWorkspaceASubscription($workspace);

        $component->call('checkConfirmation')
            ->assertSet('confirming', false)
            ->assertSet('confirmTimedOut', false)
            ->assertSee(__('Subscription active.'));
    }

    public function test_a_late_webhook_is_reconciled_straight_from_the_gateway(): void
    {
        [$owner, $workspace] = $this->owner();

        config(['cashier.api_key' => 'pdl_test_key', 'cashier.sandbox' => true]);

        // The gateway knows about the subscription even though no webhook
        // ever reached us.
        Http::fake(['*subscriptions*' => Http::response(['data' => [[
            'id' => 'sub_from_gateway',
            'status' => 'active',
            'custom_data' => ['subscription_type' => 'default'],
            'items' => [[
                'price' => ['id' => 'pri_test_1', 'product_id' => 'pro_test'],
                'status' => 'active',
                'quantity' => 1,
            ]],
        ]]])]);

        $this->actingAs($owner);

        $component = Volt::test('settings.billing')->call('paymentCompleted');

        // Polls 1-3 find nothing; the 4th reconciles.
        foreach (range(1, 4) as $ignored) {
            $component->call('checkConfirmation');
        }

        $component->assertSet('confirming', false)
            ->assertSet('confirmTimedOut', false);

        $this->assertTrue($workspace->refresh()->subscribed());
    }

    public function test_it_gives_up_gracefully_rather_than_spinning_forever(): void
    {
        [$owner] = $this->owner();

        // Nothing at the gateway either — a genuinely lost payment signal.
        config(['cashier.api_key' => 'pdl_test_key']);
        Http::fake(['*subscriptions*' => Http::response(['data' => []])]);

        $this->actingAs($owner);

        $component = Volt::test('settings.billing')->call('paymentCompleted');

        foreach (range(1, 12) as $ignored) {
            $component->call('checkConfirmation');
        }

        $component->assertSet('confirming', false)
            ->assertSet('confirmTimedOut', true)
            // Never tell someone who just paid that nothing happened.
            ->assertSee(__('Your payment went through, but we are still waiting on confirmation from the payment provider. Nothing has been lost — your plan will update shortly.'));
    }

    public function test_checkout_no_longer_hard_redirects_before_the_webhook_lands(): void
    {
        [$owner] = $this->owner();

        $this->actingAs($owner);

        // A successUrl would bounce the browser back here before the
        // subscription exists, rendering the old plan.
        $this->get(route('settings.billing'))
            ->assertOk()
            ->assertDontSee('successUrl', false);
    }

    public function test_polling_is_not_open_to_non_owners(): void
    {
        [, $workspace] = $this->owner();

        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Editor->value]);
        $member->switchToWorkspace($workspace);

        $this->actingAs($member);

        // mount() already forbids them; the guard on the methods is the
        // second line of defence.
        $this->get(route('settings.billing'))->assertForbidden();
    }
}
