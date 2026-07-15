<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-linear-to-b dark:from-zinc-950 dark:to-zinc-900">
        <div class="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 mx-auto h-96 max-w-2xl rounded-full bg-gradient-to-br from-orange-500/15 to-red-500/10 blur-3xl"></div>

            <div class="relative flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="mb-1 flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-red-600 shadow-md shadow-orange-600/20">
                        <x-app-logo-icon class="size-5 text-white" />
                    </span>
                    <span class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'QuizForge') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
