<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- No appearance script here on purpose: the quiz renders in the
             design the author published, not in the respondent's OS theme.
             With `.dark` never set, every `dark:` utility stays dormant and
             the --qf-* variables are the single source of colour. --}}
        @include('partials.head', ['appearance' => false])
    </head>
    <body class="min-h-screen bg-zinc-100 antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
