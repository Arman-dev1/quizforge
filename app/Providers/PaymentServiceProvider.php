<?php

namespace App\Providers;

use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Pushes the gateway credentials stored in the platform panel into config
 * at boot, so Cashier and the billing page read one source of truth.
 *
 * Anything left blank in the panel falls through to the existing .env
 * values, so installs that were configured before this screen existed keep
 * working without a change.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Runs before migrations exist (fresh install, `migrate` itself) and
        // during console bootstrapping — none of which should hard-fail.
        try {
            if (! Schema::hasTable('payment_settings')) {
                return;
            }

            $settings = PaymentSetting::query()->first();
        } catch (Throwable) {
            return;
        }

        if (! $settings) {
            return;
        }

        if ($settings->usesPaddle()) {
            $this->applyPaddle($settings);
        }

        if ($settings->usesStripe()) {
            $this->applyStripe($settings);
        }
    }

    protected function applyPaddle(PaymentSetting $settings): void
    {
        config([
            'cashier.seller_id' => $settings->credential('seller_id', config('cashier.seller_id')),
            'cashier.api_key' => $settings->credential('api_key', config('cashier.api_key')),
            'cashier.client_side_token' => $settings->credential('client_side_token', config('cashier.client_side_token')),
            'cashier.webhook_secret' => $settings->credential('webhook_secret', config('cashier.webhook_secret')),
            'cashier.sandbox' => $settings->test_mode,
        ]);
    }

    protected function applyStripe(PaymentSetting $settings): void
    {
        config([
            'services.stripe.key' => $settings->credential('publishable_key', config('services.stripe.key')),
            'services.stripe.secret' => $settings->credential('secret_key', config('services.stripe.secret')),
            'services.stripe.webhook_secret' => $settings->credential('webhook_secret', config('services.stripe.webhook_secret')),
        ]);
    }
}
