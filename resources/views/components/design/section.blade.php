@props(['title', 'subtitle' => null, 'icon' => null, 'pro' => false])

<div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-start gap-3">
        @if ($icon)
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon :icon="$icon" class="size-5" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ $title }}</h3>
                @if ($pro)
                    <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-amber-700 uppercase dark:bg-amber-950/60 dark:text-amber-400">{{ __('Pro') }}</span>
                @endif
            </div>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
