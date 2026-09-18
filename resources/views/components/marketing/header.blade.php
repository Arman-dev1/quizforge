@props(['c'])

@php
    $register = \Illuminate\Support\Facades\Route::has('register') ? route('register') : '#';
    $login = \Illuminate\Support\Facades\Route::has('login') ? route('login') : '#';
@endphp

<header class="sticky top-0 z-50 border-b border-zinc-100 bg-white/80 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3.5">
        <div class="flex items-center gap-9">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 text-white">
                    <flux:icon.bolt class="size-4" />
                </span>
                <span class="text-lg font-extrabold tracking-tight">{{ $c['brand'] }}</span>
            </a>
            <nav class="hidden items-center gap-7 md:flex">
                @foreach ($c['nav'] as $item)
                    {{-- On a document page the landing anchors (#pricing) would
                         go nowhere, so send them back to the home page. --}}
                    @php
                        $url = $item['url'];
                        $isAnchor = str_starts_with($url, '#');
                        $href = $isAnchor && ! request()->routeIs('home') ? route('home').$url : $url;
                    @endphp
                    <a href="{{ $href }}" class="text-sm font-semibold text-zinc-500 transition hover:text-zinc-900">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-teal-700">
                    {{ __('Open dashboard') }}<flux:icon.arrow-right class="size-4" />
                </a>
            @else
                <a href="{{ $login }}" class="hidden text-sm font-semibold text-zinc-500 transition hover:text-zinc-900 sm:inline">{{ __('Log in') }}</a>
                <a href="{{ $register }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-teal-700">
                    {{ __('Start free') }}<flux:icon.arrow-right class="size-4" />
                </a>
            @endauth
        </div>
    </div>
</header>
