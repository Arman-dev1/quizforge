<?php

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Services\Billing\UsageLimits;
use Illuminate\Support\Facades\Auth;
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

    protected function paddleConfigured(): bool
    {
        return filled(config('cashier.api_key')) && filled(config('cashier.client_side_token'));
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
        $configured = $this->paddleConfigured();
        $subscription = $workspace->subscription();

        $checkouts = [];

        if ($configured) {
            foreach (config('plans') as $key => $plan) {
                if ($key === $planKey || empty($plan['price_id'])) {
                    continue;
                }

                try {
                    $checkouts[$key] = $workspace->subscribe($plan['price_id'])
                        ->returnTo(route('settings.billing'));
                } catch (\Throwable) {
                    $configured = false;

                    break;
                }
            }
        }

        return [
            'plans' => config('plans'),
            'planKey' => $planKey,
            'usage' => $limits->usage($workspace),
            'configured' => $configured,
            'checkouts' => $checkouts,
            'subscription' => $subscription,
            'usageLabels' => [
                'quizzes' => __('Quizzes'),
                'responses_per_month' => __('Responses this month'),
                'members' => __('Team seats (incl. invites)'),
            ],
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="{{ __('Billing') }}" subheading="{{ __('Your plan, usage, and subscription') }}" wide>
        @error('billing')
            <flux:text class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        {{-- Current plan banner --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-zinc-900 px-6 py-5 text-white dark:bg-zinc-950">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-base font-extrabold">{{ __(':plan plan', ['plan' => $plans[$planKey]['name']]) }}</span>
                    <span class="rounded-md bg-white/15 px-2 py-0.5 font-mono text-[11px] font-semibold uppercase tracking-wide">{{ __('Current') }}</span>
                </div>
                <p class="mt-1.5 text-sm text-zinc-400">
                    @if ($subscription?->onGracePeriod())
                        {{ __('Cancels at the end of the billing period.') }}
                    @elseif ($subscription?->valid())
                        {{ __('Subscription active.') }}
                    @else
                        {{ __('No paid subscription.') }}
                    @endif
                </p>
            </div>

            @if ($subscription?->onGracePeriod())
                <flux:button wire:click="resume" variant="primary" size="sm">{{ __('Keep subscription') }}</flux:button>
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
                    {{ __('Upgrade to Pro') }}
                </a>
            @endif
        </div>

        {{-- Usage this month --}}
        <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Usage this month') }}</h3>
            <div class="mt-4 space-y-4">
                @foreach ($usage as $key => $meter)
                    @php
                        $limit = $meter['limit'];
                        $pct = $limit ? min(100, (int) round($meter['used'] / max($limit, 1) * 100)) : 0;
                        $over = $limit !== null && $meter['used'] >= $limit;
                    @endphp
                    <div wire:key="usage-{{ $key }}">
                        <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                            <span>{{ $usageLabels[$key] }}</span>
                            <span class="font-mono {{ $over ? 'text-red-600 dark:text-red-400' : 'text-zinc-400' }}">
                                {{ number_format($meter['used']) }} / {{ $limit === null ? __('Unlimited') : number_format($limit) }}
                            </span>
                        </div>
                        @if ($limit !== null)
                            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div @class(['h-full rounded-full', 'bg-teal-500' => ! $over, 'bg-red-500' => $over]) style="width: {{ $pct }}%"></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @unless ($configured)
            <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800/60 dark:text-zinc-300">
                {{ __('Checkout is not configured yet. Add your Paddle keys (PADDLE_SELLER_ID, PADDLE_API_KEY, PADDLE_CLIENT_SIDE_TOKEN, PADDLE_PRICE_PRO, PADDLE_PRICE_SCALE) to the .env file to enable upgrades.') }}
            </div>
        @endunless

        {{-- Plans --}}
        <div id="plans" class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($plans as $key => $plan)
                @php $popular = $key === 'pro'; @endphp
                <div @class([
                    'relative flex flex-col rounded-2xl bg-white p-5 dark:bg-zinc-900',
                    'border-2 border-teal-500 shadow-lg shadow-teal-500/20' => $popular,
                    'border border-zinc-200 dark:border-zinc-800' => ! $popular,
                ]) wire:key="plan-{{ $key }}">
                    @if ($popular)
                        <span class="absolute -top-2.5 left-5 rounded-full bg-teal-500 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                            {{ __('Most popular') }}
                        </span>
                    @endif

                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $plan['name'] }}</p>
                        @if ($key === $planKey)
                            <span class="rounded-md bg-teal-50 px-2 py-0.5 font-mono text-[10px] font-semibold uppercase text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                                {{ __('Current') }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-2 text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                        ${{ $plan['price'] }}<span class="text-sm font-normal text-zinc-400">/mo</span>
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $plan['tagline'] }}</p>

                    <ul class="mt-4 flex-1 space-y-2.5 text-xs text-zinc-600 dark:text-zinc-300">
                        <li class="flex items-center gap-2"><flux:icon.check class="size-4 text-teal-600 dark:text-teal-400" />{{ $plan['limits']['quizzes'] === null ? __('Unlimited quizzes') : trans_choice(':count quiz|:count quizzes', $plan['limits']['quizzes'], ['count' => $plan['limits']['quizzes']]) }}</li>
                        <li class="flex items-center gap-2"><flux:icon.check class="size-4 text-teal-600 dark:text-teal-400" />{{ __(':count responses / month', ['count' => number_format($plan['limits']['responses_per_month'])]) }}</li>
                        <li class="flex items-center gap-2"><flux:icon.check class="size-4 text-teal-600 dark:text-teal-400" />{{ $plan['limits']['members'] === null ? __('Unlimited team members') : trans_choice(':count team member|:count team members', $plan['limits']['members'], ['count' => $plan['limits']['members']]) }}</li>
                    </ul>

                    @if ($key === $planKey)
                        <div class="mt-5">
                            <flux:button variant="filled" size="sm" disabled class="w-full">{{ __('Current plan') }}</flux:button>
                        </div>
                    @elseif ($plan['price'] > 0)
                        <div class="mt-5">
                            @if (isset($checkouts[$key]))
                                <x-paddle-button :checkout="$checkouts[$key]" @class([
                                    'block w-full rounded-lg px-4 py-2.5 text-center text-sm font-bold',
                                    'bg-teal-500 text-white hover:bg-teal-600' => $popular,
                                    'border border-zinc-200 bg-white text-zinc-900 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white' => ! $popular,
                                ])>
                                    {{ __('Upgrade to :plan', ['plan' => $plan['name']]) }}
                                </x-paddle-button>
                            @else
                                <flux:button :variant="$popular ? 'primary' : 'filled'" size="sm" disabled class="w-full">
                                    {{ __('Checkout unavailable') }}
                                </flux:button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-settings.layout>

    @if ($configured)
        @paddleJS
    @endif
</section>
