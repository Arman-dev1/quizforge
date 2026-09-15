@props([
    'title',
    'description' => null,
])

<div class="mb-6 flex w-full flex-col gap-1.5 text-center">
    <h1 class="text-lg font-bold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
    @if ($description)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
