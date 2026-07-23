@props(['title', 'icon' => 'squares-2x2', 'tone' => 'teal'])

@php
    $toneClass = [
        'teal' => 'bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400',
        'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400',
        'zinc' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
    ][$tone] ?? 'bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3']) }}>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $toneClass }}">
        <flux:icon :icon="$icon" class="size-5" />
    </span>
    <div class="min-w-0 flex-1">
        <flux:heading>{{ $title }}</flux:heading>
        {{ $slot }}
    </div>
</div>
