@props(['model', 'options'])

<div class="mt-1.5 inline-flex flex-wrap gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
    @foreach ($options as $value => $label)
        <button
            type="button"
            x-on:click="{{ $model }} = '{{ $value }}'"
            class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
            :class="{{ $model }} === '{{ $value }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'"
        >{{ $label }}</button>
    @endforeach
</div>
