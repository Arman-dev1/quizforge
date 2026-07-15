<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-950">
        {{-- Nav --}}
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-5">
            <a href="{{ route('home') }}" class="flex items-center">
                <x-app-logo />
            </a>

            <nav class="flex items-center gap-2">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" size="sm">
                        {{ __('Open dashboard') }}
                    </flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm">
                        {{ __('Log in') }}
                    </flux:button>
                    <flux:button :href="route('register')" variant="primary" size="sm">
                        {{ __('Get started free') }}
                    </flux:button>
                @endauth
            </nav>
        </header>

        <main>
            {{-- Hero --}}
            <section class="relative mx-auto w-full max-w-6xl overflow-hidden px-6 pb-20 pt-16 text-center sm:pt-24">
                <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-24 mx-auto h-96 max-w-3xl rounded-full bg-gradient-to-br from-orange-500/25 to-red-500/10 blur-3xl"></div>

                <span class="relative inline-flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700 dark:border-orange-800 dark:bg-orange-950/60 dark:text-orange-300">
                    <x-app-logo-icon class="size-3" />
                    {{ __('Quizzes, surveys, assessments & forms — one builder') }}
                </span>

                <h1 class="relative mx-auto mt-6 max-w-3xl text-balance text-4xl font-bold tracking-tight text-zinc-900 sm:text-6xl dark:text-white">
                    {{ __('Build quizzes people') }}
                    <span class="bg-gradient-to-r from-orange-600 to-red-600 bg-clip-text text-transparent">{{ __('actually finish') }}</span>
                </h1>

                <p class="relative mx-auto mt-5 max-w-xl text-pretty text-lg text-zinc-600 dark:text-zinc-400">
                    {{ __('QuizForge helps teams create beautiful quizzes, capture qualified leads, and understand every response — without wrestling with clunky form tools.') }}
                </p>

                <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
                    <flux:button :href="route('register')" variant="primary">
                        {{ __('Start building — free') }}
                    </flux:button>
                    <flux:button :href="route('login')" variant="filled">
                        {{ __('Log in') }}
                    </flux:button>
                </div>
            </section>

            {{-- Feature grid --}}
            <section class="mx-auto w-full max-w-6xl px-6 pb-24">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['icon' => 'squares-plus', 'title' => __('Visual builder'), 'text' => __('22 question types, multi-page flows, and instant autosave. No manual needed.')],
                        ['icon' => 'user-plus', 'title' => __('Lead capture'), 'text' => __('Turn every quiz into a lead magnet — even partial responses capture contacts.')],
                        ['icon' => 'chart-bar', 'title' => __('Deep analytics'), 'text' => __('Completion rates, drop-off points, and question performance at a glance.')],
                        ['icon' => 'arrows-pointing-out', 'title' => __('Logic & scoring'), 'text' => __('Branching, skip logic, weighted scores, grades, and personality outcomes.')],
                        ['icon' => 'users', 'title' => __('Built for teams'), 'text' => __('Workspaces with roles and permissions, from solo creators to whole departments.')],
                        ['icon' => 'bolt', 'title' => __('Fast everywhere'), 'text' => __('Lightweight quiz pages that load in under a second on any device.')],
                    ] as $feature)
                        <div class="rounded-2xl border border-zinc-200 bg-white p-6 transition hover:border-orange-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-orange-900">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-950/60">
                                <flux:icon :icon="$feature['icon']" class="size-5 text-orange-600 dark:text-orange-400" />
                            </span>
                            <h2 class="mt-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ $feature['title'] }}</h2>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-200 py-8 dark:border-zinc-800">
            <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">
                &copy; {{ date('Y') }} QuizForge
            </p>
        </footer>

        @fluxScripts
    </body>
</html>
