<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <div class="flex items-center gap-1">
                <div class="min-w-0 flex-1">
                    <livewire:workspace-switcher />
                </div>
                <livewire:notification-bell key="bell-desktop" />
            </div>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="{{ __('Platform') }}" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="puzzle-piece" :href="route('quizzes.index')" :current="request()->routeIs('quizzes.*')" wire:navigate>{{ __('Quizzes') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-plus" :href="route('leads.index')" :current="request()->routeIs('leads.*')" wire:navigate>{{ __('Leads') }}</flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            {{-- Plan usage + upgrade (Clean Slate) --}}
            @php
                $usageWorkspace = auth()->user()->currentWorkspace;
                $usageLimits = app(\App\Services\Billing\UsageLimits::class);
                $usageQuizLimit = $usageWorkspace ? $usageLimits->limit($usageWorkspace, 'quizzes') : null;
            @endphp
            @if ($usageWorkspace && $usageQuizLimit !== null)
                @php
                    $usageQuizUsed = $usageLimits->quizCount($usageWorkspace);
                    $usagePct = min(100, (int) round($usageQuizUsed / max($usageQuizLimit, 1) * 100));
                    $usageOver = $usageQuizUsed >= $usageQuizLimit;
                @endphp
                <div class="mb-2 rounded-xl border border-teal-100 bg-teal-50 p-3.5 dark:border-teal-900/60 dark:bg-teal-950/40">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold text-zinc-700 dark:text-zinc-200">
                        {{ __('Quizzes') }}
                        <span class="{{ $usageOver ? 'text-red-600 dark:text-red-400' : 'text-teal-600 dark:text-teal-400' }}">{{ $usageQuizUsed }} / {{ $usageQuizLimit }}</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/60">
                        <div class="h-full {{ $usageOver ? 'bg-red-500' : 'bg-teal-500' }}" style="width: {{ $usagePct }}%"></div>
                    </div>
                    <flux:button :href="route('settings.billing')" wire:navigate variant="primary" size="sm" class="mt-3 w-full">
                        {{ __('Upgrade plan') }}
                    </flux:button>
                </div>
            @endif

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notification-bell key="bell-mobile" />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
