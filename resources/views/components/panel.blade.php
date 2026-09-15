@props([
    'title' => null,
    'description' => null,
    'icon' => null,
    'flush' => false,
])

{{--
  | The standard content surface. `flush` drops the body padding for panels
  | whose content is a full-bleed list or table.
  | Slots: default = body, `actions` = controls in the panel header.
--}}
<section {{ $attributes->merge(['class' => 'qf-surface']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="flex min-w-0 items-center gap-3">
                @if ($icon)
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                        <flux:icon :icon="$icon" class="size-4.5" />
                    </span>
                @endif
                <div class="min-w-0">
                    @if ($title)
                        <h2 class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
                    @endif
                </div>
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => ! $flush])>
        {{ $slot }}
    </div>
</section>
