<?php

namespace App\Models;

use App\Services\Billing\UsageLimits;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan — the super-admin-managed source of truth for limits,
 * seats, pricing, and the paid/free feature gates (integrations, custom
 * code). When no plan rows exist (fresh install / tests) callers fall back
 * to config('plans'); `toConfigArray()` keeps both shapes identical.
 */
class Plan extends Model
{
    protected $fillable = [
        'key', 'name', 'tagline', 'price', 'period', 'price_id', 'stripe_price_id',
        'quizzes', 'responses_per_month', 'members',
        'integrations', 'custom_code', 'remove_branding',
        'features', 'is_popular', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quizzes' => 'integer',
            'responses_per_month' => 'integer',
            'members' => 'integer',
            'integrations' => 'boolean',
            'custom_code' => 'boolean',
            'remove_branding' => 'boolean',
            'features' => 'array',
            'is_popular' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return Collection<int, self> */
    public static function active(): Collection
    {
        return static::query()->where('is_active', true)->orderBy('position')->orderBy('id')->get();
    }

    /** Whether the DB holds managed plans (else callers use config defaults). */
    public static function anyDefined(): bool
    {
        return static::query()->exists();
    }

    /**
     * The price id for whichever gateway is currently active. Callers
     * (UsageLimits, the billing page) never need to know which one it is.
     */
    public function activePriceId(): ?string
    {
        return PaymentSetting::current()->usesStripe()
            ? $this->stripe_price_id
            : $this->price_id;
    }

    /**
     * Resolve a gateway price id back to the plan it belongs to.
     *
     * The platform panel reads subscriptions from the gateway, which only
     * knows price ids — staff want the plan name. Matched against either
     * column so a subscription bought under the other gateway still names
     * the right plan. Memoized: a listing asks this once per row.
     *
     * @return array<string, self> price id => plan
     */
    public static function byPriceId(): array
    {
        return once(fn () => static::query()
            ->get()
            ->flatMap(fn (self $plan) => array_filter([
                $plan->price_id => $plan,
                $plan->stripe_price_id => $plan,
            ], fn ($value, $key) => filled($key), ARRAY_FILTER_USE_BOTH))
            ->all());
    }

    /** The plan name for a price id, or the raw id when nothing matches. */
    public static function nameForPriceId(?string $priceId): ?string
    {
        if (blank($priceId)) {
            return null;
        }

        return static::byPriceId()[$priceId]?->name ?? $priceId;
    }

    /** Config-shaped representation so UsageLimits treats DB + config alike. */
    public function toConfigArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'period' => $this->period,
            'price_id' => $this->activePriceId(),
            'tagline' => $this->tagline,
            'popular' => $this->is_popular,
            'limits' => [
                'quizzes' => $this->quizzes,
                'responses_per_month' => $this->responses_per_month,
                'members' => $this->members,
            ],
            'flags' => [
                'integrations' => $this->integrations,
                'custom_code' => $this->custom_code,
                'remove_branding' => $this->remove_branding,
            ],
            'features' => $this->features ?? [],
        ];
    }

    /**
     * Plans for display (public pricing section, billing page): managed
     * rows if defined, otherwise the config defaults. Resolution lives in
     * UsageLimits so the pricing a visitor sees and the limits we enforce
     * can never come from different sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forDisplay(): array
    {
        return collect(app(UsageLimits::class)->allPlans())
            ->map(fn (array $plan, string $key) => ['key' => $key] + $plan)
            ->values()
            ->all();
    }
}
