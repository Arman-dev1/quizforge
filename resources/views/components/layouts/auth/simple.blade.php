<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-linear-to-b dark:from-zinc-950 dark:to-zinc-900">
        <div class="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
            {{-- Brand wash, kept in the teal family — the old gradient still
                 carried a red stop from the pre-"Clean Slate" palette. --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 mx-auto h-96 max-w-2xl rounded-full bg-gradient-to-br from-teal-500/20 to-teal-300/10 blur-3xl"></div>

            <div class="relative flex w-full max-w-sm flex-col gap-5">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 shadow-md shadow-teal-600/20">
                        <x-app-logo-icon class="size-5.5 text-white" />
                    </span>
                    <span class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'QuizForge') }}</span>
                </a>

                {{-- The form sits on a real surface rather than floating on
                     the page ground, so the fields read as one grouped task. --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-7 dark:border-zinc-800 dark:bg-zinc-900">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
