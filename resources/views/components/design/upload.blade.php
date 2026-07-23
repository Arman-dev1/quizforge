@props(['model', 'urlVar', 'removeKey', 'label'])

<div>
    <x-design.label>{{ $label }}</x-design.label>
    <div class="mt-1.5">
        <template x-if="{{ $urlVar }}">
            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                <img :src="{{ $urlVar }}" alt="" class="h-10 w-16 rounded object-cover" />
                <span class="min-w-0 flex-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __('Uploaded') }}</span>
                <flux:button variant="subtle" size="xs" icon="trash" wire:click="removeAsset('{{ $removeKey }}')" aria-label="{{ __('Remove :label', ['label' => $label]) }}" />
            </div>
        </template>
        <template x-if="!{{ $urlVar }}">
            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-zinc-300 px-3 py-4 text-sm text-zinc-500 transition hover:border-teal-400 hover:text-teal-600 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-teal-700">
                <flux:icon.arrow-up-tray class="size-4" />
                <span wire:loading.remove wire:target="{{ $model }}">{{ __('Upload') }}</span>
                <span wire:loading wire:target="{{ $model }}">{{ __('Uploading…') }}</span>
                <input type="file" wire:model="{{ $model }}" accept="image/*" class="hidden" />
            </label>
        </template>
    </div>
    @error($model)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
