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

    <x-settings.layout heading="{{ __('Billing') }}" subheading="{{ __('Your plan, usage, and subscription') }}">
        @error('billing')
            <flux:text class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        {{-- Current subscription state --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div>
                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                    {{ __('Current plan: :plan', ['plan' => $plans[$planKey]['name']]) }}
                </p>
                @if ($subscription?->onGracePeriod())
                    <p class="text-xs text-amber-600 dark:text-amber-400">
                        {{ __('Cancels at the end of the billing period.') }}
                    </p>
                @elseif ($subscription?->valid())
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Subscription active.') }}</p>
                @else
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('No paid subscription.') }}</p>
                @endif
            </div>

            @if ($subscription?->onGracePeriod())
                <flux:button wire:click="resume" variant="primary" size="sm">{{ __('Keep subscription') }}</flux:button>
            @elseif ($subscription?->valid())
                <flux:button
                    wire:click="cancel"
                    wire:confirm="{{ __('Cancel your subscription? You keep access until the period ends.') }}"
                    variant="filled"
                    size="sm"
                >
                    {{ __('Cancel subscription') }}
                </flux:button>
            @endif
        </div>

        {{-- Usage meters --}}
        <div class="mt-4 space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            @foreach ($usage as $key => $meter)
                @php
                    $limit = $meter['limit'];
                    $pct = $limit ? min(100, (int) round($meter['used'] / max($limit, 1) * 100)) : 0;
                @endphp
                <div wire:key="usage-{{ $key }}">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $usageLabels[$key] }}</span>
                        <span class="tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ number_format($meter['used']) }} / {{ $limit === null ? __('Unlimited') : number_format($limit) }}
                        </span>
                    </div>
                    @if ($limit !== null)
                        <div class="mt-1 h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div @class(['h-full rounded-full', 'bg-orange-600' => $pct < 100, 'bg-red-600' => $pct >= 100]) style="width: {{ $pct }}%"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @unless ($configured)
            <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">
                {{ __('Checkout is not configured yet. Add your Paddle keys (PADDLE_SELLER_ID, PADDLE_API_KEY, PADDLE_CLIENT_SIDE_TOKEN, PADDLE_PRICE_PRO, PADDLE_PRICE_SCALE) to the .env file to enable upgrades.') }}
            </div>
        @endunless

        {{-- Plans --}}
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($plans as $key => $plan)
                <div @class([
                    'flex flex-col rounded-xl border p-4',
                    'border-orange-500 ring-1 ring-orange-500' => $key === $planKey,
                    'border-zinc-200 dark:border-zinc-700' => $key !== $planKey,
                ]) wire:key="plan-{{ $key }}">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $plan['name'] }}</p>
                        @if ($key === $planKey)
                            <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800 dark:bg-orange-950/60 dark:text-orange-300">
                                {{ __('Current') }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-2 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        ${{ $plan['price'] }}<span class="text-sm font-normal text-zinc-400">/mo</span>
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $plan['tagline'] }}</p>

                    <ul class="mt-3 flex-1 space-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                        <li>{{ $plan['limits']['quizzes'] === null ? __('Unlimited quizzes') : trans_choice(':count quiz|:count quizzes', $plan['limits']['quizzes'], ['count' => $plan['limits']['quizzes']]) }}</li>
                        <li>{{ __(':count responses / month', ['count' => number_format($plan['limits']['responses_per_month'])]) }}</li>
                        <li>{{ $plan['limits']['members'] === null ? __('Unlimited team members') : trans_choice(':count team member|:count team members', $plan['limits']['members'], ['count' => $plan['limits']['members']]) }}</li>
                    </ul>

                    @if ($key !== $planKey && $plan['price'] > 0)
                        <div class="mt-4">
                            @if (isset($checkouts[$key]))
                                <x-paddle-button :checkout="$checkouts[$key]" class="w-full rounded-lg bg-orange-700 px-4 py-2 text-sm font-medium text-white hover:bg-orange-800">
                                    {{ __('Upgrade to :plan', ['plan' => $plan['name']]) }}
                                </x-paddle-button>
                            @else
                                <flux:button variant="filled" size="sm" disabled class="w-full">
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
