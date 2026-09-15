<?php

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Services\Billing\UsageLimits;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        abort_unless($this->isOwner(), 403);
    }

    protected function isOwner(): bool
    {
        $workspace = Auth::user()->currentWorkspace;

        return $workspace && Auth::user()->roleIn($workspace) === WorkspaceRole::Owner;
    }

    protected function workspace(): Workspace
    {
        return Auth::user()->currentWorkspace;
    }

    /**
     * Checkout is available only when the platform has an active gateway
     * with complete credentials (managed at /super-admin → Payments).
     * Falls back to the .env values for installs configured before that
     * screen existed.
     */
    protected function gateway(): \App\Models\PaymentSetting
    {
        return \App\Models\PaymentSetting::current();
    }

    protected function checkoutConfigured(): bool
    {
        $gateway = $this->gateway();

        if ($gateway->isConfigured()) {
            return $gateway->usesPaddle();
        }

        // No gateway chosen in the panel yet — honour legacy .env config.
        return $gateway->provider === \App\Models\PaymentSetting::PROVIDER_NONE
            && filled(config('cashier.api_key'))
            && filled(config('cashier.client_side_token'));
    }

    /*
     |----------------------------------------------------------------------
     | Waiting for a just-completed checkout
     |----------------------------------------------------------------------
     | Paddle confirms the payment in the browser, but the subscription only
     | reaches us via a webhook — which arrives after, and sometimes not at
     | all (a dropped tunnel in development, a delivery failure in
     | production). Without this the page shows the old plan until someone
     | thinks to reload, which reads as "my payment did nothing".
     */
    public bool $confirming = false;

    public int $confirmAttempts = 0;

    public bool $confirmTimedOut = false;

    /** How many 2s polls before we stop waiting. */
    protected const MAX_CONFIRM_ATTEMPTS = 12;

    /** The poll at which we stop waiting on the webhook and ask Paddle directly. */
    protected const RECONCILE_AFTER = 4;

    /** Called from the browser when the Paddle overlay reports success. */
    public function paymentCompleted(): void
    {
        abort_unless($this->isOwner(), 403);

        $this->confirming = true;
        $this->confirmAttempts = 0;
        $this->confirmTimedOut = false;
    }

    public function checkConfirmation(): void
    {
        abort_unless($this->isOwner(), 403);

        if (! $this->confirming) {
            return;
        }

        $this->confirmAttempts++;

        if ($this->workspace()->refresh()->subscribed()) {
            $this->confirming = false;

            return;
        }

        // The webhook is late or lost — read the truth from the gateway
        // instead of waiting on it. Same reconciliation the platform panel
        // and app:sync-subscriptions use.
        if ($this->confirmAttempts === self::RECONCILE_AFTER) {
            try {
                Artisan::call('app:sync-subscriptions', ['--workspace' => $this->workspace()->id]);
            } catch (\Throwable $e) {
                Log::warning('Could not reconcile subscription after checkout', [
                    'workspace_id' => $this->workspace()->id,
                    'message' => $e->getMessage(),
                ]);
            }

            if ($this->workspace()->refresh()->subscribed()) {
                $this->confirming = false;

                return;
            }
        }

        if ($this->confirmAttempts >= self::MAX_CONFIRM_ATTEMPTS) {
            $this->confirming = false;
            $this->confirmTimedOut = true;
        }
    }

    public function cancel(): void
    {
        abort_unless($this->isOwner(), 403);

        try {
            $this->workspace()->subscription()?->cancel();
        } catch (\Throwable) {
            $this->addError('billing', __('Could not reach the billing provider. Please try again.'));
        }
    }

    public function resume(): void
    {
        abort_unless($this->isOwner(), 403);

        try {
            $this->workspace()->subscription()?->stopCancelation();
        } catch (\Throwable) {
            $this->addError('billing', __('Could not reach the billing provider. Please try again.'));
        }
    }

    public function with(UsageLimits $limits): array
    {
        $workspace = $this->workspace();
        $planKey = $limits->planKey($workspace);
        $gateway = $this->gateway();
        $configured = $this->checkoutConfigured();
        $subscription = $workspace->subscription();

        // Managed plans when the super-admin has defined them, else the
        // shipped config — same source UsageLimits enforces against.
        $plans = $limits->allPlans();

        $checkouts = [];
        $missingPriceIds = [];

        if ($configured) {
            foreach ($plans as $key => $plan) {
                if ($key === $planKey) {
                    continue;
                }

                // A paid plan with no price id is a platform misconfiguration,
                // not a gateway outage — worth telling them apart.
                if (($plan['price'] ?? 0) > 0 && empty($plan['price_id'])) {
                    $missingPriceIds[] = $plan['name'] ?? $key;

                    continue;
                }

                if (empty($plan['price_id'])) {
                    continue;
                }

                try {
                    $checkouts[$key] = $workspace->subscribe($plan['price_id'])
                        ->returnTo(route('settings.billing'));
                } catch (\Throwable $e) {
                    /*
                     | Never swallow this silently: it is the difference
                     | between "no gateway set up" and "the gateway rejected
                     | us", and without the log there is nothing to debug.
                     */
                    \Illuminate\Support\Facades\Log::error('Checkout could not be prepared', [
                        'workspace_id' => $workspace->id,
                        'plan' => $key,
                        'price_id' => $plan['price_id'],
                        'gateway' => $gateway->provider,
                        'message' => $e->getMessage(),
                    ]);

                    $configured = false;

                    break;
                }
            }
        }

        return [
            'plans' => $plans,
            'currentPlan' => $plans[$planKey] ?? ['name' => __('Free')],
            'planKey' => $planKey,
            'usage' => $limits->usage($workspace),
            'configured' => $configured,
            'gateway' => $gateway,
            'checkouts' => $checkouts,
            'missingPriceIds' => $missingPriceIds,
            'subscription' => $subscription,
            'usageLabels' => [
                'quizzes' => __('Quizzes'),
                'responses_per_month' => __('Responses this month'),
                'members' => __('Team seats (incl. invites)'),
            ],
        ];
    }
}; ?>

<section
    class="w-full"
    {{-- Paddle tells the browser the moment the overlay succeeds; the
         subscription itself lands a beat later via webhook. --}}
    x-on:paddle-checkout-completed.window="$wire.paymentCompleted()"
>
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Billing')" :subheading="__('Your plan, what you are using, and your subscription.')" wide>
        @error('billing')
            <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        @if ($confirming)
            <div
                wire:poll.2s="checkConfirmation"
                class="flex items-center gap-3 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3.5 dark:border-teal-900/60 dark:bg-teal-950/30"
                role="status"
                aria-live="polite"
            >
                <svg class="size-5 shrink-0 animate-spin text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4Z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold text-teal-900 dark:text-teal-200">{{ __('Payment received — activating your plan…') }}</p>
                    <p class="text-xs text-teal-800/80 dark:text-teal-300/80">{{ __('This usually takes a few seconds. You do not need to refresh.') }}</p>
                </div>
            </div>
        @endif

        @if ($confirmTimedOut)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/30">
                <div class="flex min-w-0 items-start gap-2.5">
                    <flux:icon.clock class="mt-0.5 size-4.5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <p class="text-sm text-amber-900 dark:text-amber-200">
                        {{ __('Your payment went through, but we are still waiting on confirmation from the payment provider. Nothing has been lost — your plan will update shortly.') }}
                    </p>
                </div>
                <flux:button wire:click="paymentCompleted" size="sm" variant="filled" icon="arrow-path">
                    {{ __('Check again') }}
                </flux:button>
            </div>
        @endif

        {{-- Current plan: the one thing you came here to check. --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-zinc-900 px-6 py-5 text-white dark:bg-zinc-900 dark:ring-1 dark:ring-zinc-800">
            <div class="min-w-0">
                <p class="qf-eyebrow !text-zinc-400">{{ __('Current plan') }}</p>
                <div class="mt-1.5 flex flex-wrap items-baseline gap-2">
                    <span class="text-xl font-extrabold tracking-tight">{{ $currentPlan['name'] }}</span>
                    @if (($currentPlan['price'] ?? 0) > 0)
                        <span class="qf-num text-sm text-zinc-400">${{ $currentPlan['price'] }}/{{ $currentPlan['period'] ?? 'mo' }}</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-zinc-400">
                    @if ($subscription?->onGracePeriod())
                        {{ __('Cancels at the end of the current billing period.') }}
                    @elseif ($subscription?->valid())
                        {{ __('Subscription active.') }}
                    @else
                        {{ __('No paid subscription — you are on the free tier.') }}
                    @endif
                </p>
            </div>

            @if ($subscription?->onGracePeriod())
                <flux:button wire:click="resume" variant="primary">{{ __('Keep subscription') }}</flux:button>
            @elseif ($subscription?->valid())
                <x-confirm
                    action="cancel"
                    :title="__('Cancel your subscription?')"
                    :description="__('You keep full access until the end of the current billing period, then move to the Free plan.')"
                    :confirm="__('Cancel subscription')"
                    icon="x-circle"
                >
                    <x-slot:trigger>
                        <button type="button" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                            {{ __('Cancel subscription') }}
                        </button>
                    </x-slot:trigger>
                </x-confirm>
            @else
                <a href="#plans" class="rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-teal-600">
                    {{ __('See plans') }}
                </a>
            @endif
        </div>

        <x-panel :title="__('Usage this month')" icon="chart-bar">
            <div class="flex flex-col gap-5">
                @foreach ($usage as $key => $meter)
                    @php
                        $limit = $meter['limit'];
                        $pct = $limit ? min(100, (int) round($meter['used'] / max($limit, 1) * 100)) : 0;
                        $over = $limit !== null && $meter['used'] >= $limit;
                        $near = ! $over && $limit !== null && $pct >= 80;
                    @endphp
                    <div wire:key="usage-{{ $key }}">
                        <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $usageLabels[$key] }}</span>
                            <span @class([
                                'qf-num font-semibold',
                                'text-red-600 dark:text-red-400' => $over,
                                'text-amber-600 dark:text-amber-400' => $near,
                                'text-zinc-400' => ! $over && ! $near,
                            ])>
                                {{ number_format($meter['used']) }} / {{ $limit === null ? __('Unlimited') : number_format($limit) }}
                            </span>
                        </div>

                        @if ($limit !== null)
                            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div @class([
                                    'h-full rounded-full transition-all',
                                    'bg-red-500' => $over,
                                    'bg-amber-500' => $near,
                                    'bg-teal-600' => ! $over && ! $near,
                                ]) style="width: {{ $pct }}%"></div>
                            </div>

                            @if ($over)
                                <p class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                                    {{ __('Limit reached — upgrade to keep going.') }}
                                </p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </x-panel>

        @unless ($configured)
            {{-- Customers should not be told to edit .env — that is a platform
                 concern. Say what is true from their side. --}}
            <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/30">
                <flux:icon.information-circle class="mt-0.5 size-4.5 shrink-0 text-amber-600 dark:text-amber-400" />
                <p class="text-sm text-amber-900 dark:text-amber-200">
                    @if ($gateway->usesStripe())
                        {{ __('Card checkout is being set up. Everything on your current plan keeps working — please check back shortly.') }}
                    @else
                        {{ __('Online checkout is not available yet. Everything on your current plan keeps working — get in touch if you would like to upgrade.') }}
                    @endif
                </p>
            </div>
        @endunless

        <div id="plans" class="flex flex-col gap-4">
            <h2 class="text-base font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Plans') }}</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($plans as $key => $plan)
                    @php
                        $popular = $plan['popular'] ?? false;
                        $isCurrent = $key === $planKey;
                    @endphp

                    <div @class([
                        'relative flex flex-col rounded-xl bg-white p-5 dark:bg-zinc-900',
                        'border-2 border-teal-600 dark:border-teal-500' => $popular && ! $isCurrent,
                        'border-2 border-zinc-900 dark:border-zinc-100' => $isCurrent,
                        'border border-zinc-200 dark:border-zinc-800' => ! $popular && ! $isCurrent,
                    ]) wire:key="plan-{{ $key }}">
                        @if ($popular && ! $isCurrent)
                            <span class="absolute -top-2.5 left-5 rounded-full bg-teal-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                {{ __('Most popular') }}
                            </span>
                        @endif

                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $plan['name'] }}</p>
                            @if ($isCurrent)
                                <span class="rounded-md bg-zinc-900 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white dark:bg-white dark:text-zinc-900">
                                    {{ __('Current') }}
                                </span>
                            @endif
                        </div>

                        <p class="qf-num mt-3 text-3xl font-extrabold text-zinc-900 dark:text-white">
                            ${{ $plan['price'] }}<span class="text-sm font-normal text-zinc-400">/{{ $plan['period'] ?? 'mo' }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $plan['tagline'] ?? '' }}</p>

                        <ul class="mt-5 flex flex-1 flex-col gap-2.5 text-xs text-zinc-600 dark:text-zinc-300">
                            @forelse ($plan['features'] ?? [] as $feature)
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="mt-px size-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                    <span>{{ $feature }}</span>
                                </li>
                            @empty
                                {{-- No marketing bullets set: fall back to the limits we actually enforce. --}}
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="mt-px size-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                    <span>{{ $plan['limits']['quizzes'] === null ? __('Unlimited quizzes') : trans_choice(':count quiz|:count quizzes', $plan['limits']['quizzes'], ['count' => $plan['limits']['quizzes']]) }}</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="mt-px size-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                    <span>{{ $plan['limits']['responses_per_month'] === null ? __('Unlimited responses') : __(':count responses / month', ['count' => number_format($plan['limits']['responses_per_month'])]) }}</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="mt-px size-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                    <span>{{ $plan['limits']['members'] === null ? __('Unlimited team members') : trans_choice(':count team member|:count team members', $plan['limits']['members'], ['count' => $plan['limits']['members']]) }}</span>
                                </li>
                            @endforelse
                        </ul>

                        <div class="mt-5">
                            @if ($isCurrent)
                                <flux:button variant="filled" disabled class="w-full justify-center">{{ __('Your plan') }}</flux:button>
                            @elseif (($plan['price'] ?? 0) > 0)
                                @if (isset($checkouts[$key]))
                                    <x-checkout-button :checkout="$checkouts[$key]" class="block w-full rounded-lg bg-teal-600 px-4 py-2.5 text-center text-sm font-bold text-white transition hover:bg-teal-700">
                                        {{ __('Upgrade to :plan', ['plan' => $plan['name']]) }}
                                    </x-checkout-button>
                                @else
                                    <flux:button variant="filled" disabled class="w-full justify-center">
                                        {{ __('Checkout unavailable') }}
                                    </flux:button>
                                @endif
                            @else
                                <flux:button variant="filled" disabled class="w-full justify-center">{{ __('Free tier') }}</flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-settings.layout>

    {{-- Paddle.js is loaded once in the layout head (<x-paddle-js>), not here:
         injecting the CDN script into the body breaks under wire:navigate. --}}
</section>
