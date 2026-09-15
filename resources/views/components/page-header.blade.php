@props([
    'title',
    'description' => null,
    'back' => null,
    'backLabel' => null,
])

{{--
  | The single page-title pattern for the whole app. Before this, each page
  | rolled its own heading block and they drifted — different sizes, different
  | gaps, actions sometimes above and sometimes below the title.
  |
  | Slots: default = actions (right-aligned), `meta` = a line of context under
  | the title, `tabs` = a sub-navigation row that sits flush with the divider.
--}}
<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($back)
        <a
            href="{{ $back }}"
            wire:navigate
            class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"
        >
            <flux:icon.chevron-left class="size-4" />
            {{ $backLabel ?? __('Back') }}
        </a>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ $title }}
            </h1>

            @if ($description)
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
            @endif

            @isset($meta)
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $meta }}
                </div>
            @endisset
        </div>

        @if (trim($slot) !== '')
            <div class="flex flex-wrap items-center gap-2">
                {{ $slot }}
            </div>
        @endif
    </div>

    @isset($tabs)
        <div class="qf-scroll-x mt-6 border-b border-zinc-200 dark:border-zinc-800">
            <nav class="flex items-center gap-6" aria-label="{{ __('Section') }}">
                {{ $tabs }}
            </nav>
        </div>
    @endisset
</div>
