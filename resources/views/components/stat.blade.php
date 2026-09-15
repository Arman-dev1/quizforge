@props([
    'label',
    'value',
    'icon' => null,
    'hint' => null,
    'delta' => null,
    'tone' => 'neutral',
    'href' => null,
    'limit' => null,
])

@php
    // Meters only when a limit exists — a bar with no ceiling says nothing.
    $used = is_numeric($value) ? (int) $value : null;
    $pct = $limit && $used !== null ? min(100, (int) round($used / max($limit, 1) * 100)) : null;
    $over = $limit !== null && $used !== null && $used >= $limit;

    $deltaTone = [
        'up' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-950/50',
        'down' => 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-950/50',
        'neutral' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-300 dark:bg-zinc-800',
    ][$tone] ?? 'text-zinc-600 bg-zinc-100 dark:text-zinc-300 dark:bg-zinc-800';

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => ($href ? 'qf-surface-interactive block' : 'qf-surface').' p-4']) }}
>
    <div class="flex items-center justify-between gap-2">
        <span class="truncate text-[13px] font-semibold text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
        @if ($icon)
            <flux:icon :icon="$icon" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
        @endif
    </div>

    <div class="mt-2.5 flex items-baseline gap-2">
        <span class="qf-num text-[28px] font-extrabold leading-none text-zinc-900 dark:text-white">{{ $value }}</span>

        @if ($limit !== null)
            <span class="qf-num text-sm font-medium text-zinc-400">/ {{ number_format($limit) }}</span>
        @endif

        @if ($delta)
            <span class="ml-auto rounded-md px-1.5 py-0.5 text-[11px] font-bold {{ $deltaTone }}">{{ $delta }}</span>
        @endif
    </div>

    @if ($pct !== null)
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div @class(['h-full rounded-full transition-all', 'bg-teal-600' => ! $over, 'bg-red-500' => $over]) style="width: {{ $pct }}%"></div>
        </div>
    @elseif ($hint)
        <p class="mt-2 truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
