<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-svh bg-zinc-50 antialiased lg:h-svh lg:overflow-hidden dark:bg-zinc-950">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
