<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The active payment gateway and its credentials.
 *
 * A single row managed by platform staff at /super-admin. Keys live here
 * rather than in .env so they can be rotated without a deploy; both
 * providers' keys are kept so switching gateways is reversible.
 *
 * Values fall back to the environment when a field is blank, which keeps
 * existing .env-based installs working untouched.
 */
class PaymentSetting extends Model
{
    public const PROVIDER_NONE = 'none';

    public const PROVIDER_PADDLE = 'paddle';

    public const PROVIDER_STRIPE = 'stripe';

    protected $fillable = ['provider', 'test_mode', 'paddle', 'stripe', 'verified_at'];

    protected function casts(): array
    {
        return [
            'test_mode' => 'boolean',
            'verified_at' => 'datetime',
            // Live API secrets: never at rest in plain text.
            'paddle' => 'encrypted:array',
            'stripe' => 'encrypted:array',
        ];
    }

    /** Container key for the per-request memo. */
    protected const CACHE_KEY = 'payment.settings.current';

    /**
     * The one-and-only settings row, created on first read and memoized for
     * the request — every plan asks which gateway is active, and that must
     * not be a query per plan.
     *
     * Memoized in the container rather than a static property so it is
     * scoped to the request (and reset between tests) automatically.
     */
    public static function current(): self
    {
        if (app()->bound(self::CACHE_KEY)) {
            return app(self::CACHE_KEY);
        }

        $settings = static::query()->firstOr(fn () => static::query()->create([
            'provider' => self::PROVIDER_NONE,
            'test_mode' => true,
        ]));

        app()->instance(self::CACHE_KEY, $settings);

        return $settings;
    }

    /** Drop the memo, so the next read reflects a just-saved change. */
    public static function forgetCached(): void
    {
        app()->forgetInstance(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCached());
        static::deleted(fn () => static::forgetCached());
    }

    /** @return array<string, string> the credentials for the active provider */
    public function credentials(): array
    {
        return array_filter((array) ($this->{$this->provider} ?? []), fn ($value) => filled($value));
    }

    public function credential(string $key, ?string $fallback = null): ?string
    {
        return $this->credentials()[$key] ?? $fallback;
    }

    public function usesPaddle(): bool
    {
        return $this->provider === self::PROVIDER_PADDLE;
    }

    public function usesStripe(): bool
    {
        return $this->provider === self::PROVIDER_STRIPE;
    }

    /**
     * Whether the active provider has everything it needs to take a payment.
     * The billing page renders checkout as unavailable until this is true.
     */
    public function isConfigured(): bool
    {
        return match ($this->provider) {
            self::PROVIDER_PADDLE => filled($this->credential('seller_id'))
                && filled($this->credential('api_key'))
                && filled($this->credential('client_side_token')),
            self::PROVIDER_STRIPE => filled($this->credential('publishable_key'))
                && filled($this->credential('secret_key')),
            default => false,
        };
    }

    /** Human label for the active provider. */
    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_PADDLE => 'Paddle',
            self::PROVIDER_STRIPE => 'Stripe',
            default => __('No gateway'),
        };
    }

    /** Fields required per provider, used by the panel form and validation. */
    public static function requiredFields(string $provider): array
    {
        return match ($provider) {
            self::PROVIDER_PADDLE => ['seller_id', 'api_key', 'client_side_token'],
            self::PROVIDER_STRIPE => ['publishable_key', 'secret_key'],
            default => [],
        };
    }
}
