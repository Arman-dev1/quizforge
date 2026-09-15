@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'compact' => false,
])

{{-- Empty states are a real screen, not an apology. Icon, what this is,
     what to do next — the action slot carries the next step. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center '.($compact ? 'px-6 py-10' : 'px-6 py-16')]) }}>
    <span class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
        <flux:icon :icon="$icon" class="size-6" />
    </span>

    <h3 class="mt-4 text-base font-bold text-zinc-900 dark:text-white">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-1.5 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
