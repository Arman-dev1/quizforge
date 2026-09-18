@php
    $register = \Illuminate\Support\Facades\Route::has('register') ? route('register') : '#';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $page->title.' · '.$c['brand']])
        @if ($page->excerpt)
            <meta name="description" content="{{ $page->excerpt }}" />
        @endif
        <style>
            html { scroll-behavior: smooth; }
            body { background: #ffffff; }
        </style>
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">

        <x-marketing.header :c="$c" />

        {{-- ─────────────── Title ─────────────── --}}
        <section class="border-b border-zinc-100 bg-gradient-to-b from-teal-50/60 to-white px-6 py-14 sm:py-20">
            <div class="mx-auto max-w-3xl text-center">
                <h1 class="text-4xl font-extrabold tracking-tight text-zinc-900 sm:text-5xl">{{ $page->title }}</h1>
                @if ($page->excerpt)
                    <p class="mx-auto mt-4 max-w-2xl text-lg leading-relaxed text-zinc-500">{{ $page->excerpt }}</p>
                @endif
                <p class="mt-6 text-xs text-zinc-400">
                    {{ __('Last updated :date', ['date' => $page->updated_at?->format('j F Y')]) }}
                </p>
            </div>
        </section>

        {{-- ─────────────── Integration cards ─────────────── --}}
        @if ($providers !== [])
            <section class="px-6 pt-14">
                <div class="mx-auto max-w-5xl">
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($providers as $provider)
                            <div class="group rounded-2xl border border-zinc-200 bg-white p-6 transition hover:border-teal-300 hover:shadow-lg hover:shadow-teal-100/50">
                                <div class="flex items-start justify-between gap-3">
                                    <span
                                        class="flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-extrabold"
                                        style="background: {{ $provider['color'] }}; color: {{ $provider['ink'] }}"
                                    >{{ $provider['initial'] }}</span>
                                    @if ($provider['popular'])
                                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-bold text-teal-700">{{ __('Popular') }}</span>
                                    @endif
                                </div>
                                <h3 class="mt-4 text-base font-bold text-zinc-900">{{ $provider['name'] }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $provider['description'] }}</p>
                            </div>
                        @endforeach

                        {{-- Honest about the edges: says what is not here yet. --}}
                        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-300 p-6 text-center">
                            <flux:icon.link class="size-6 text-zinc-300" />
                            <h3 class="mt-3 text-base font-bold text-zinc-900">{{ __('Need another tool?') }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">
                                {{ __('Every response is exportable as CSV, so you can move data anywhere in the meantime.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ─────────────── Body ─────────────── --}}
        @if ($page->body)
            <section class="px-6 py-14 sm:py-16">
                {{-- Sanitized by ContentPage on save; never render author HTML
                     that has not been through HtmlSanitizer. --}}
                <article class="qf-doc mx-auto max-w-3xl">
                    {!! $page->body !!}
                </article>
            </section>
        @endif

        {{-- ─────────────── CTA ─────────────── --}}
        <section class="px-6 pb-16">
            <div class="mx-auto max-w-3xl rounded-2xl border border-zinc-200 bg-zinc-50 px-8 py-10 text-center">
                <h2 class="text-2xl font-extrabold tracking-tight text-zinc-900">{{ $c['cta_title'] }}</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-zinc-500">{{ $c['cta_subtitle'] }}</p>
                <a href="{{ $register }}" class="mt-6 inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-teal-700">
                    {{ $c['cta_primary_label'] }}<flux:icon.arrow-right class="size-4" />
                </a>
            </div>
        </section>

        <x-marketing.footer :c="$c" :pages="$footerPages" />

        @fluxScripts
    </body>
</html>
