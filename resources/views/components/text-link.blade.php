<a
    {{ $attributes->merge(['class' => 'text-sm font-semibold text-teal-700 underline decoration-teal-700/30 underline-offset-2 transition hover:decoration-teal-700 dark:text-teal-400 dark:decoration-teal-400/30 dark:hover:decoration-teal-400']) }}
    wire:navigate
>
    {{ $slot }}
</a>
