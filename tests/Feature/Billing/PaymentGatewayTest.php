<?php

namespace Tests\Feature\Billing;

use App\Enums\WorkspaceRole;
use App\Filament\Resources\PaymentSettings\Pages\ManagePaymentSettings;
use App\Models\PaymentSetting;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Workspace;
use App\Providers\PaymentServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function ownerWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create();
        $user->switchToWorkspace($workspace);

        return [$user, $workspace];
    }

    public function test_it_starts_with_no_gateway(): void
    {
        $settings = PaymentSetting::current();

        $this->assertSame(PaymentSetting::PROVIDER_NONE, $settings->provider);
        $this->assertTrue($settings->test_mode);
        $this->assertFalse($settings->isConfigured());
    }

    public function test_super_admins_can_reach_the_payments_screen(): void
    {
        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        Livewire::test(ManagePaymentSettings::class)->assertOk();

        $this->get('/super-admin/payment-settings')->assertOk();
    }

    public function test_customers_cannot_reach_the_payments_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/super-admin/payment-settings')->assertRedirect();
    }

    public function test_paddle_is_only_configured_once_every_required_key_is_present(): void
    {
        $settings = PaymentSetting::current();

        $settings->update([
            'provider' => PaymentSetting::PROVIDER_PADDLE,
            'paddle' => ['seller_id' => '12345', 'api_key' => 'pdl_secret'],
        ]);

        $this->assertFalse($settings->isConfigured(), 'client_side_token is still missing');

        $settings->update([
            'paddle' => ['seller_id' => '12345', 'api_key' => 'pdl_secret', 'client_side_token' => 'live_token'],
        ]);

        $this->assertTrue($settings->fresh()->isConfigured());
    }

    public function test_stripe_is_only_configured_once_every_required_key_is_present(): void
    {
        $settings = PaymentSetting::current();

        $settings->update([
            'provider' => PaymentSetting::PROVIDER_STRIPE,
            'stripe' => ['publishable_key' => 'pk_test_123'],
        ]);

        $this->assertFalse($settings->isConfigured(), 'secret_key is still missing');

        $settings->update([
            'stripe' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123'],
        ]);

        $this->assertTrue($settings->fresh()->isConfigured());
    }

    public function test_switching_gateways_keeps_the_other_providers_keys(): void
    {
        $settings = PaymentSetting::current();

        $settings->update([
            'provider' => PaymentSetting::PROVIDER_PADDLE,
            'paddle' => ['seller_id' => '1', 'api_key' => 'a', 'client_side_token' => 'b'],
        ]);

        $settings->update([
            'provider' => PaymentSetting::PROVIDER_STRIPE,
            'stripe' => ['publishable_key' => 'pk', 'secret_key' => 'sk'],
        ]);

        $fresh = $settings->fresh();

        $this->assertSame('1', $fresh->paddle['seller_id']);
        $this->assertSame('pk', $fresh->stripe['publishable_key']);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        PaymentSetting::current()->update([
            'provider' => PaymentSetting::PROVIDER_STRIPE,
            'stripe' => ['publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_SUPERSECRET'],
        ]);

        $raw = DB::table('payment_settings')->value('stripe');

        $this->assertStringNotContainsString('sk_test_SUPERSECRET', (string) $raw);
        $this->assertSame('sk_test_SUPERSECRET', PaymentSetting::current()->credential('secret_key'));
    }

    public function test_stored_paddle_keys_reach_the_cashier_config(): void
    {
        PaymentSetting::current()->update([
            'provider' => PaymentSetting::PROVIDER_PADDLE,
            'test_mode' => false,
            'paddle' => [
                'seller_id' => '99999',
                'api_key' => 'pdl_from_db',
                'client_side_token' => 'token_from_db',
            ],
        ]);

        // Re-boot the provider the way a fresh request would.
        (new PaymentServiceProvider($this->app))->boot();

        $this->assertSame('99999', config('cashier.seller_id'));
        $this->assertSame('pdl_from_db', config('cashier.api_key'));
        $this->assertSame('token_from_db', config('cashier.client_side_token'));
        $this->assertFalse(config('cashier.sandbox'));
    }

    public function test_a_plan_resolves_the_price_id_of_the_active_gateway(): void
    {
        $plan = Plan::query()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'price' => 29,
            'price_id' => 'pri_paddle_123',
            'stripe_price_id' => 'price_stripe_456',
            'quizzes' => 20,
            'responses_per_month' => 1000,
            'members' => 3,
        ]);

        $settings = PaymentSetting::current();

        $settings->update(['provider' => PaymentSetting::PROVIDER_PADDLE]);
        $this->assertSame('pri_paddle_123', $plan->activePriceId());
        $this->assertSame('pri_paddle_123', $plan->toConfigArray()['price_id']);

        $settings->update(['provider' => PaymentSetting::PROVIDER_STRIPE]);
        $this->assertSame('price_stripe_456', $plan->activePriceId());
        $this->assertSame('price_stripe_456', $plan->toConfigArray()['price_id']);
    }

    /**
     * The workspace is the Paddle billable but has no email column of its
     * own. Without this, Cashier throws "Unable to create Paddle customer
     * without an email" and checkout could never start.
     */
    public function test_a_workspace_bills_to_its_owner(): void
    {
        $owner = User::factory()->create(['name' => 'Ada Owner', 'email' => 'ada@example.com']);
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();

        $this->assertSame('ada@example.com', $workspace->paddleEmail());
        $this->assertSame('Ada Owner', $workspace->paddleName());
        $this->assertSame($owner->id, $workspace->billingContact()->id);
    }

    public function test_billing_falls_back_to_any_member_when_there_is_no_owner(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Editor->value]);

        $this->assertSame('member@example.com', $workspace->paddleEmail());
    }

    public function test_paddle_email_is_null_for_an_empty_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $this->assertNull($workspace->paddleEmail());
    }

    public function test_the_billing_page_hides_checkout_when_no_gateway_is_active(): void
    {
        [$owner] = $this->ownerWithWorkspace();

        // No gateway chosen and no legacy .env keys.
        config(['cashier.api_key' => null, 'cashier.client_side_token' => null]);

        $this->actingAs($owner);

        Volt::test('settings.billing')
            ->assertOk()
            ->assertSet('configured', false)
            ->assertSee(__('Online checkout is not available yet. Everything on your current plan keeps working — get in touch if you would like to upgrade.'));
    }

    public function test_the_billing_page_never_leaks_env_instructions_to_customers(): void
    {
        [$owner] = $this->ownerWithWorkspace();

        PaymentSetting::current()->update(['provider' => PaymentSetting::PROVIDER_STRIPE]);

        $this->actingAs($owner);

        $this->get(route('settings.billing'))
            ->assertOk()
            ->assertDontSee('.env')
            ->assertDontSee('PADDLE_API_KEY');
    }
}
