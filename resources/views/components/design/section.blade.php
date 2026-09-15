@props(['title', 'subtitle' => null, 'icon' => null, 'pro' => false])

{{-- Design-tab section. Kept separate from <x-panel> because it carries the
     Pro badge and a tighter header for a control-dense column. --}}
<section class="qf-surface">
    <header class="flex items-start gap-3 border-b border-zinc-200 px-5 py-3.5 dark:border-zinc-800">
        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon :icon="$icon" class="size-4.5" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ $title }}</h3>
                @if ($pro)
                    <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">{{ __('Pro') }}</span>
                @endif
            </div>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
            @endif
        </div>
    </header>

    <div class="p-5">
        {{ $slot }}
    </div>
</section>
