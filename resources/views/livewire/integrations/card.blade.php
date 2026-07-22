<div class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900" wire:key="int-{{ $p['key'] }}">
    <div class="flex items-center gap-3">
        {!! $logo($p) !!}
        <div class="min-w-0">
            <p class="truncate font-bold text-zinc-900 dark:text-white">{{ $p['name'] }}</p>
            @if ($p['connected'])
                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-teal-200 bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 dark:border-teal-900 dark:bg-teal-950/60 dark:text-teal-300">
                    <span class="size-1.5 rounded-full bg-teal-500"></span>{{ __('Connected') }}
                </span>
            @else
                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>{{ __('Not connected') }}
                </span>
            @endif
        </div>
    </div>

    <p class="mt-3 flex-1 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $p['description'] }}</p>

    <div class="mt-4 flex items-center gap-2">
        @if ($canManage)
            @if ($p['connected'])
                <flux:button size="sm" variant="primary" icon="cog-6-tooth" wire:click="configure('{{ $p['key'] }}')">{{ __('Configure') }}</flux:button>
                <flux:button size="sm" variant="filled" wire:click="askDisconnect('{{ $p['key'] }}')">{{ __('Disconnect') }}</flux:button>
            @else
                <flux:button size="sm" variant="primary" icon="plus" wire:click="configure('{{ $p['key'] }}')" class="w-full justify-center">{{ __('Connect') }}</flux:button>
            @endif
        @else
            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Only workspace admins can manage integrations.') }}</span>
        @endif
    </div>
</div>
