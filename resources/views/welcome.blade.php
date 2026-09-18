@php
    $c = \App\Models\SiteSetting::current()->content();
    $plans = \App\Models\Plan::forDisplay();
    $register = \Illuminate\Support\Facades\Route::has('register') ? route('register') : '#';
    $login = \Illuminate\Support\Facades\Route::has('login') ? route('login') : '#';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            html { scroll-behavior: smooth; }
            body { background: #ffffff; }
        </style>
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">

        {{-- ─────────────── Nav ─────────────── --}}
        <x-marketing.header :c="$c" />

        <main>
            {{-- ─────────────── Hero ─────────────── --}}
            <section id="top" class="relative overflow-hidden px-6 pt-24 pb-16 text-center" style="background: radial-gradient(60% 60% at 50% 0%, #e6fbf7 0%, rgba(255,255,255,0) 70%);">
                <div class="mx-auto max-w-3xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-teal-100 bg-white px-4 py-1.5 text-sm font-semibold text-teal-700 shadow-sm">
                        <flux:icon.sparkles class="size-4" />{{ $c['hero_badge'] }}
                    </span>
                    <h1 class="mt-7 text-5xl leading-[1.05] font-extrabold tracking-tight text-balance sm:text-6xl">
                        {{ $c['hero_title'] }}
                        <span class="bg-gradient-to-r from-teal-600 via-teal-700 to-amber-500 bg-clip-text text-transparent">{{ $c['hero_highlight'] }}</span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-zinc-500 text-pretty">{{ $c['hero_subtitle'] }}</p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ $register }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-6 py-3.5 text-base font-bold text-white shadow-lg shadow-teal-600/30 transition hover:bg-teal-700">
                            {{ $c['hero_primary_label'] }}<flux:icon.arrow-right class="size-5" />
                        </a>
                        <a href="{{ $c['hero_secondary_url'] }}" class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-6 py-3.5 text-base font-semibold text-zinc-900 transition hover:bg-zinc-50">
                            <flux:icon.play-circle class="size-5 text-teal-600" />{{ $c['hero_secondary_label'] }}
                        </a>
                    </div>
                    <p class="mt-5 text-xs text-zinc-400">{{ $c['hero_note'] }}</p>
                </div>

                {{-- Product mock --}}
                <div class="mx-auto mt-16 max-w-5xl">
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 shadow-2xl shadow-zinc-900/20">
                        <div class="flex items-center gap-2 border-b border-zinc-100 bg-white px-4 py-3">
                            <span class="size-3 rounded-full bg-red-400"></span>
                            <span class="size-3 rounded-full bg-amber-400"></span>
                            <span class="size-3 rounded-full bg-green-400"></span>
                            <span class="ml-3 text-xs text-zinc-400">quizforge.devcorex.in/dashboard</span>
                        </div>
                        <div class="grid gap-4 p-5 text-left sm:grid-cols-3">
                            @foreach ([['Responses', '1,284', '↗ 12%', 'text-green-600'], ['Completion', '87%', '↗ 5%', 'text-green-600'], ['Leads', '342', '↗ 8%', 'text-amber-600']] as [$label, $value, $delta, $tone])
                                <div class="rounded-xl border border-zinc-100 bg-white p-4">
                                    <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">{{ $label }}</p>
                                    <p class="mt-1 text-2xl font-extrabold">{{ $value }}</p>
                                    <p class="mt-0.5 text-xs font-bold {{ $tone }}">{{ $delta }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- ─────────────── Logos ─────────────── --}}
            <section class="border-y border-zinc-100 px-6 py-11">
                <p class="text-center text-[11px] font-medium tracking-[0.12em] text-zinc-400 uppercase">{{ $c['logos_heading'] }}</p>
                <div class="mx-auto mt-6 flex max-w-4xl flex-wrap items-center justify-center gap-x-11 gap-y-5 opacity-60">
                    @foreach ($c['logos'] as $logo)
                        <span class="inline-flex items-center gap-2 text-xl font-extrabold tracking-tight text-zinc-500">
                            @if (! empty($logo['icon']))<flux:icon :icon="$logo['icon']" class="size-5" />@endif{{ $logo['name'] }}
                        </span>
                    @endforeach
                </div>
            </section>

            {{-- ─────────────── Features ─────────────── --}}
            <section id="features" class="px-6 py-24">
                <div class="mx-auto max-w-6xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="text-xs font-semibold tracking-[0.1em] text-teal-600 uppercase">{{ $c['features_eyebrow'] }}</span>
                        <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-balance">{{ $c['features_title'] }}</h2>
                        <p class="mt-4 text-lg leading-relaxed text-zinc-500">{{ $c['features_subtitle'] }}</p>
                    </div>
                    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($c['features'] as $feature)
                            <div class="rounded-2xl border border-zinc-100 bg-white p-7 transition hover:border-teal-200 hover:shadow-lg hover:shadow-zinc-900/5">
                                <span @class([
                                    'flex size-11 items-center justify-center rounded-xl',
                                    'bg-teal-50 text-teal-600' => ($feature['tone'] ?? 'teal') !== 'amber',
                                    'bg-amber-50 text-amber-600' => ($feature['tone'] ?? 'teal') === 'amber',
                                ])>
                                    <flux:icon :icon="$feature['icon']" class="size-5" />
                                </span>
                                <h3 class="mt-4 text-lg font-bold">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-500">{{ $feature['text'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ─────────────── How it works ─────────────── --}}
            <section id="how" class="bg-zinc-950 px-6 py-24 text-white">
                <div class="mx-auto max-w-5xl">
                    <div class="mx-auto max-w-xl text-center">
                        <span class="text-xs font-semibold tracking-[0.1em] text-teal-400 uppercase">{{ $c['how_eyebrow'] }}</span>
                        <h2 class="mt-3 text-4xl font-extrabold tracking-tight">{{ $c['how_title'] }}</h2>
                    </div>
                    <div class="mt-14 grid gap-7 md:grid-cols-3">
                        @foreach ($c['steps'] as $step)
                            <div>
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="flex size-9 items-center justify-center rounded-xl bg-teal-400/15 text-sm font-semibold text-teal-300">{{ $step['number'] }}</span>
                                    <span class="h-px flex-1 bg-white/10"></span>
                                </div>
                                <h3 class="text-xl font-bold">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $step['text'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ─────────────── Testimonials ─────────────── --}}
            <section class="px-6 py-24">
                <div class="mx-auto max-w-5xl">
                    <div class="mx-auto max-w-xl text-center">
                        <span class="text-xs font-semibold tracking-[0.1em] text-teal-600 uppercase">{{ $c['testimonials_eyebrow'] }}</span>
                        <h2 class="mt-3 text-4xl font-extrabold tracking-tight">{{ $c['testimonials_title'] }}</h2>
                    </div>
                    <div class="mt-12 grid gap-5 md:grid-cols-3">
                        @foreach ($c['testimonials'] as $t)
                            <figure class="rounded-2xl border border-zinc-100 bg-zinc-50 p-7">
                                <div class="mb-3.5 flex gap-0.5 text-amber-500">
                                    @for ($i = 0; $i < 5; $i++)<flux:icon.star variant="solid" class="size-4" />@endfor
                                </div>
                                <blockquote class="mb-5 text-[15px] leading-relaxed text-zinc-700">“{{ $t['quote'] }}”</blockquote>
                                <figcaption class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-teal-400 to-teal-600 text-sm font-bold text-white">{{ $t['initials'] }}</span>
                                    <span>
                                        <span class="block text-sm font-bold">{{ $t['name'] }}</span>
                                        <span class="block text-xs text-zinc-400">{{ $t['role'] }}</span>
                                    </span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ─────────────── Pricing ─────────────── --}}
            <section id="pricing" class="border-t border-zinc-100 bg-zinc-50 px-6 py-24">
                <div class="mx-auto max-w-5xl">
                    <div class="mx-auto max-w-xl text-center">
                        <span class="text-xs font-semibold tracking-[0.1em] text-teal-600 uppercase">{{ $c['pricing_eyebrow'] }}</span>
                        <h2 class="mt-3 text-4xl font-extrabold tracking-tight">{{ $c['pricing_title'] }}</h2>
                        <p class="mt-4 text-lg text-zinc-500">{{ $c['pricing_subtitle'] }}</p>
                    </div>
                    <div class="mx-auto mt-12 grid max-w-3xl items-start gap-5 sm:grid-cols-2">
                        @foreach ($plans as $plan)
                            @php($popular = $plan['popular'] ?? false)
                            <div @class([
                                'relative rounded-2xl bg-white p-7',
                                'border border-zinc-200' => ! $popular,
                                'border-2 border-teal-600 shadow-xl shadow-teal-600/20' => $popular,
                            ])>
                                @if ($popular)
                                    <span class="absolute -top-3 left-7 rounded-full bg-teal-600 px-3 py-1 text-[11px] font-bold tracking-wide text-white">MOST POPULAR</span>
                                @endif
                                <h3 class="text-base font-bold">{{ $plan['name'] }}</h3>
                                <p class="mt-1.5 text-xs text-zinc-400">{{ $plan['tagline'] }}</p>
                                <div class="my-5 flex items-baseline gap-1">
                                    <span class="text-4xl font-extrabold tracking-tight">${{ $plan['price'] }}</span>
                                    <span class="text-sm text-zinc-400">/{{ $plan['period'] ?? 'mo' }}</span>
                                </div>
                                <a href="{{ $register }}" @class([
                                    'mb-6 block rounded-xl py-2.5 text-center text-sm font-bold transition',
                                    'bg-teal-600 text-white shadow-md shadow-teal-600/40 hover:bg-teal-700' => $popular,
                                    'border border-zinc-200 text-zinc-900 hover:bg-zinc-50' => ! $popular,
                                ])>{{ ($plan['key'] ?? '') === 'free' ? __('Get started') : __('Upgrade to :name', ['name' => $plan['name']]) }}</a>
                                <ul class="flex flex-col gap-3 text-sm text-zinc-600">
                                    @foreach ($plan['features'] ?? [] as $line)
                                        <li class="flex gap-2.5"><flux:icon.check class="size-4 shrink-0 text-teal-600" />{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ─────────────── FAQ ─────────────── --}}
            <section id="faq" class="px-6 py-24">
                <div class="mx-auto max-w-2xl">
                    <div class="mb-12 text-center">
                        <span class="text-xs font-semibold tracking-[0.1em] text-teal-600 uppercase">{{ $c['faq_eyebrow'] }}</span>
                        <h2 class="mt-3 text-4xl font-extrabold tracking-tight">{{ $c['faq_title'] }}</h2>
                    </div>
                    <div class="flex flex-col gap-3">
                        @foreach ($c['faqs'] as $i => $faq)
                            <details class="group rounded-2xl border border-zinc-100 px-5" @if ($i === 0) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between py-4 text-base font-bold">
                                    {{ $faq['question'] }}
                                    <flux:icon.plus class="size-5 text-zinc-400 transition group-open:rotate-45" />
                                </summary>
                                <p class="-mt-1 pb-4 text-[15px] leading-relaxed text-zinc-500">{{ $faq['answer'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ─────────────── CTA ─────────────── --}}
            <section class="px-6 pt-10 pb-24">
                <div class="relative mx-auto max-w-5xl overflow-hidden rounded-3xl px-10 py-16 text-center" style="background: linear-gradient(135deg,#0f766e,#14b8a6);">
                    <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/10"></div>
                    <div class="pointer-events-none absolute -bottom-20 -left-8 size-52 rounded-full bg-amber-400/15"></div>
                    <div class="relative">
                        <h2 class="text-4xl font-extrabold tracking-tight text-balance text-white">{{ $c['cta_title'] }}</h2>
                        <p class="mx-auto mt-4 max-w-md text-lg leading-relaxed text-white/85">{{ $c['cta_subtitle'] }}</p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <a href="{{ $register }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-base font-bold text-teal-700 transition hover:bg-teal-50">
                                {{ $c['cta_primary_label'] }}<flux:icon.arrow-right class="size-5" />
                            </a>
                            <a href="{{ $c['cta_secondary_url'] }}" class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3.5 text-base font-semibold text-white transition hover:bg-white/20">{{ $c['cta_secondary_label'] }}</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        {{-- ─────────────── Footer ─────────────── --}}
        <x-marketing.footer :c="$c" />

        @fluxScripts
    </body>
</html>
