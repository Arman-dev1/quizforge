@props([
    'action',
    'title' => __('Are you sure?'),
    'description' => '',
    'confirm' => __('Confirm'),
    'cancel' => __('Cancel'),
    'tone' => 'danger',
    'icon' => 'exclamation-triangle',
    'name' => null,
])

@php($__modal = $name ?: 'confirm-'.substr(md5($action.'|'.$title), 0, 12))

<flux:modal.trigger name="{{ $__modal }}">
    {{ $trigger }}
</flux:modal.trigger>

<flux:modal name="{{ $__modal }}" class="w-full max-w-md">
    <div class="space-y-5">
        <div class="flex gap-3.5">
            <span @class([
                'flex size-10 shrink-0 items-center justify-center rounded-full',
                'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400' => $tone === 'danger',
                'bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400' => $tone !== 'danger',
            ])>
                <flux:icon :icon="$icon" class="size-5" />
            </span>
            <div class="min-w-0">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                @if ($description)
                    <flux:subheading class="mt-1">{{ $description }}</flux:subheading>
                @endif
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ $cancel }}</flux:button>
            </flux:modal.close>

            <flux:modal.close>
                <flux:button :variant="$tone" wire:click="{{ $action }}">{{ $confirm }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
