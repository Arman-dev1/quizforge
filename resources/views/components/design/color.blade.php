@props(['label', 'model'])

<div>
    <x-design.label>{{ $label }}</x-design.label>
    <div class="mt-1.5 flex items-center gap-2 rounded-lg border border-zinc-200 p-1.5 dark:border-zinc-700">
        <input type="color" x-model="{{ $model }}" aria-label="{{ $label }}" class="size-8 shrink-0 cursor-pointer rounded-md border-0 bg-transparent p-0" />
        <input type="text" x-model="{{ $model }}" spellcheck="false" aria-label="{{ $label }} hex" class="min-w-0 flex-1 border-0 bg-transparent p-0 font-mono text-sm text-zinc-700 uppercase focus:ring-0 dark:text-zinc-200" />
    </div>
</div>
