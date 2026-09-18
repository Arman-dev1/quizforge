@props(['c', 'pages' => null])

@php
    // Documents the owner has chosen to surface. Passed in on document pages;
    // resolved here on the landing page so welcome.blade.php stays declarative.
    $pages ??= \App\Models\ContentPage::inFooter()->get();

    // A landing-page anchor (#pricing) points nowhere from a document page,
    // so send those back to the home page — same rule as the header.
    $onHome = request()->routeIs('home');

    $columns = collect($c['footer_columns'] ?? [])
        ->map(fn (array $col) => [
            'heading' => $col['heading'] ?? '',
            // Drop placeholder links rather than shipping dead "#" anchors.
            'links' => collect($col['links'] ?? [])
                ->reject(fn (array $link) => trim($link['url'] ?? '') === '' || trim($link['url']) === '#')
                ->map(function (array $link) use ($onHome) {
                    $link['url'] = str_starts_with($link['url'], '#') && ! $onHome
                        ? route('home').$link['url']
                        : $link['url'];

                    return $link;
                })
                ->values()
                ->all(),
        ])
        ->reject(fn (array $col) => $col['links'] === [])
        ->values();
@endphp

<footer class="border-t border-zinc-100 px-6 pt-14 pb-8">
    <div class="mx-auto grid max-w-6xl gap-10 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr]">
        <div>
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex size-7 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 text-white">
                    <flux:icon.bolt class="size-4" />
                </span>
                <span class="text-lg font-extrabold tracking-tight">{{ $c['brand'] }}</span>
            </a>
            <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-400">{{ $c['footer_tagline'] }}</p>
        </div>

        @foreach ($columns as $col)
            <div>
                <p class="mb-3.5 text-[11px] font-semibold tracking-[0.08em] text-zinc-400 uppercase">{{ $col['heading'] }}</p>
                <div class="flex flex-col gap-2.5">
                    @foreach ($col['links'] as $link)
                        <a href="{{ $link['url'] }}" class="text-sm text-zinc-500 transition hover:text-zinc-900">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($pages->isNotEmpty())
            <div>
                <p class="mb-3.5 text-[11px] font-semibold tracking-[0.08em] text-zinc-400 uppercase">{{ __('Company') }}</p>
                <div class="flex flex-col gap-2.5">
                    @foreach ($pages as $page)
                        <a href="{{ $page->url() }}" class="text-sm text-zinc-500 transition hover:text-zinc-900">{{ $page->label() }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="mx-auto mt-11 max-w-6xl border-t border-zinc-100 pt-6">
        <span class="text-sm text-zinc-400">{{ $c['footer_copyright'] }}</span>
    </div>
</footer>
