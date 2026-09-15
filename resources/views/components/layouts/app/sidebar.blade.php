@php
    use App\Enums\WorkspaceRole;

    $user = auth()->user();
    $workspace = $user->currentWorkspace;
    $role = $workspace ? $user->roleIn($workspace) : null;
    $limits = app(\App\Services\Billing\UsageLimits::class);

    $quizLimit = $workspace ? $limits->limit($workspace, 'quizzes') : null;
    $responseLimit = $workspace ? $limits->limit($workspace, 'responses_per_month') : null;
    $planName = $workspace ? ($limits->plan($workspace)['name'] ?? __('Free')) : __('Free');
    $isOwner = $role === WorkspaceRole::Owner;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        {{-- Loaded once here so it survives wire:navigate page swaps. --}}
        <x-paddle-js />
    </head>
    {{-- Tinted ground so white panels read as raised content, not as the page itself. --}}
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        <x-impersonation-banner />
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" wire:navigate>
                    <x-app-logo class="size-8" />
                </a>
                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
            </div>

            {{-- Workspace context: which tenant am I in, and can I switch? --}}
            <div class="mt-5 flex items-center gap-1.5">
                <div class="min-w-0 flex-1">
                    <livewire:workspace-switcher />
                </div>
                <livewire:notification-bell key="bell-desktop" />
            </div>

            {{-- The primary action lives above the nav, where every tool of
                 this kind puts it. Nothing else in the rail competes with it. --}}
            @can('create', App\Models\Quiz::class)
                <flux:button
                    :href="route('quizzes.create')"
                    wire:navigate
                    variant="primary"
                    icon="plus"
                    class="mt-3 w-full justify-center"
                >
                    {{ __('New quiz') }}
                </flux:button>
            @endcan

            <flux:navlist variant="outline" class="mt-5">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navlist.item>
                <flux:navlist.item icon="puzzle-piece" :href="route('quizzes.index')" :current="request()->routeIs('quizzes.*')" wire:navigate>
                    {{ __('Quizzes') }}
                </flux:navlist.item>
                <flux:navlist.item icon="user-plus" :href="route('leads.index')" :current="request()->routeIs('leads.*')" wire:navigate>
                    {{ __('Leads') }}
                </flux:navlist.item>
            </flux:navlist>

            <flux:navlist variant="outline" class="mt-1">
                <flux:navlist.group :heading="__('Workspace')" class="grid">
                    <flux:navlist.item icon="users" :href="route('settings.members')" :current="request()->routeIs('settings.members')" wire:navigate>
                        {{ __('Members') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="cog-6-tooth" :href="route('settings.workspace')" :current="request()->routeIs('settings.workspace')" wire:navigate>
                        {{ __('Settings') }}
                    </flux:navlist.item>
                    @if ($isOwner)
                        <flux:navlist.item icon="credit-card" :href="route('settings.billing')" :current="request()->routeIs('settings.billing')" wire:navigate>
                            {{ __('Billing') }}
                        </flux:navlist.item>
                    @endif
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            {{-- Usage: both metered limits, not just quizzes. Only shown when
                 there is a ceiling to show — an unlimited plan gets nothing. --}}
            @if ($workspace && ($quizLimit !== null || $responseLimit !== null))
                @php
                    $meters = array_filter([
                        $quizLimit !== null ? ['label' => __('Quizzes'), 'used' => $limits->quizCount($workspace), 'limit' => $quizLimit] : null,
                        $responseLimit !== null ? ['label' => __('Responses'), 'used' => $limits->responsesThisMonth($workspace), 'limit' => $responseLimit] : null,
                    ]);
                    $anyOver = collect($meters)->contains(fn ($m) => $m['used'] >= $m['limit']);
                @endphp

                <div class="qf-well mb-3 p-3.5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="qf-eyebrow">{{ $planName }} {{ __('plan') }}</span>
                        @if ($anyOver)
                            <span class="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-600 dark:bg-red-950/60 dark:text-red-400">
                                {{ __('Full') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2.5">
                        @foreach ($meters as $meter)
                            @php
                                $pct = min(100, (int) round($meter['used'] / max($meter['limit'], 1) * 100));
                                $over = $meter['used'] >= $meter['limit'];
                            @endphp
                            <div>
                                <div class="mb-1 flex items-baseline justify-between text-[11px] font-semibold">
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $meter['label'] }}</span>
                                    <span @class(['qf-num', 'text-red-600 dark:text-red-400' => $over, 'text-zinc-500 dark:text-zinc-400' => ! $over])>
                                        {{ number_format($meter['used']) }}/{{ number_format($meter['limit']) }}
                                    </span>
                                </div>
                                <div class="h-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                    <div @class(['h-full rounded-full', 'bg-teal-600' => ! $over, 'bg-red-500' => $over]) style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($isOwner)
                        <flux:button :href="route('settings.billing')" wire:navigate size="sm" class="mt-3 w-full justify-center">
                            {{ __('Upgrade') }}
                        </flux:button>
                    @endif
                </div>
            @endif

            <flux:dropdown position="top" align="start">
                <flux:profile
                    :name="$user->name"
                    :initials="$user->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[240px]">
                    <div class="px-2 py-1.5">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('settings.profile')" icon="user-circle" wire:navigate>{{ __('Profile') }}</flux:menu.item>
                    <flux:menu.item :href="route('settings.notifications')" icon="bell" wire:navigate>{{ __('Notifications') }}</flux:menu.item>
                    <flux:menu.item :href="route('settings.appearance')" icon="swatch" wire:navigate>{{ __('Appearance') }}</flux:menu.item>

                    {{-- Only shown to staff who actually hold a platform
                         session right now, not to any customer account. --}}
                    @if (auth('super_admin')->check())
                        <flux:menu.separator />
                        <flux:menu.item href="/super-admin" icon="shield-check">{{ __('Platform panel') }}</flux:menu.item>
                    @endif

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        {{-- Mobile bar: the rail is stashed, so the toggle, the bell and the
             account menu have to live here. --}}
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ml-1 flex items-center" wire:navigate>
                <x-app-logo class="size-7" />
            </a>

            <flux:spacer />

            <livewire:notification-bell key="bell-mobile" />

            <flux:dropdown position="bottom" align="end">
                <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

                <flux:menu class="w-[240px]">
                    <div class="px-2 py-1.5">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('settings.profile')" icon="user-circle" wire:navigate>{{ __('Profile') }}</flux:menu.item>
                    <flux:menu.item :href="route('settings.appearance')" icon="swatch" wire:navigate>{{ __('Appearance') }}</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
