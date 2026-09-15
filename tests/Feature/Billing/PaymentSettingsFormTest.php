<?php

namespace Tests\Feature\Billing;

use App\Filament\Resources\PaymentSettings\Pages\ManagePaymentSettings;
use App\Models\PaymentSetting;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the panel form itself, not just the list page — a missing form
 * component class only blows up once the edit modal is opened.
 */
class PaymentSettingsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function panel(): Testable
    {
        $this->actingAs(SuperAdmin::factory()->create(), 'super_admin');

        return Livewire::test(ManagePaymentSettings::class);
    }

    public function test_the_edit_form_opens_and_is_populated(): void
    {
        $settings = PaymentSetting::current();
        $settings->update([
            'provider' => PaymentSetting::PROVIDER_PADDLE,
            'paddle' => ['seller_id' => '4242', 'api_key' => 'k', 'client_side_token' => 't'],
        ]);

        // Mounting is what would surface a missing form-component class.
        $this->panel()
            ->mountTableAction('edit', $settings->fresh())
            ->assertHasNoTableActionErrors()
            ->assertTableActionDataSet(fn (array $data) => $data['provider'] === PaymentSetting::PROVIDER_PADDLE
                && ($data['paddle']['seller_id'] ?? null) === '4242');
    }

    public function test_paddle_credentials_can_be_saved_from_the_panel(): void
    {
        $settings = PaymentSetting::current();

        $this->panel()
            ->mountTableAction('edit', $settings)
            ->setTableActionData([
                'provider' => PaymentSetting::PROVIDER_PADDLE,
                'test_mode' => true,
                'paddle' => [
                    'seller_id' => '12345',
                    'client_side_token' => 'live_abc',
                    'api_key' => 'pdl_secret_key',
                    'webhook_secret' => 'pdl_notif_secret',
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        PaymentSetting::forgetCached();
        $fresh = PaymentSetting::current();

        $this->assertTrue($fresh->usesPaddle());
        $this->assertTrue($fresh->isConfigured());
        $this->assertSame('pdl_secret_key', $fresh->credential('api_key'));
    }

    public function test_stripe_credentials_can_be_saved_from_the_panel(): void
    {
        $settings = PaymentSetting::current();

        $this->panel()
            ->mountTableAction('edit', $settings)
            ->setTableActionData([
                'provider' => PaymentSetting::PROVIDER_STRIPE,
                'test_mode' => true,
                'stripe' => [
                    'publishable_key' => 'pk_test_abc',
                    'secret_key' => 'sk_test_abc',
                    'webhook_secret' => 'whsec_abc',
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        PaymentSetting::forgetCached();
        $fresh = PaymentSetting::current();

        $this->assertTrue($fresh->usesStripe());
        $this->assertTrue($fresh->isConfigured());
        $this->assertSame('sk_test_abc', $fresh->credential('secret_key'));
    }

    public function test_required_credentials_are_enforced(): void
    {
        $settings = PaymentSetting::current();

        $this->panel()
            ->mountTableAction('edit', $settings)
            ->setTableActionData([
                'provider' => PaymentSetting::PROVIDER_PADDLE,
                'paddle' => ['seller_id' => '', 'client_side_token' => '', 'api_key' => ''],
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors();
    }
}
