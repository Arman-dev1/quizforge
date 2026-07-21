<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function switch(int $workspaceId): void
    {
        $workspace = Auth::user()->workspaces()->whereKey($workspaceId)->firstOrFail();

        Auth::user()->switchToWorkspace($workspace);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function with(): array
    {
        return [
            'workspaces' => Auth::user()->workspaces()->orderBy('name')->get(),
            'current' => Auth::user()->currentWorkspace,
        ];
    }
}; ?>

<div class="mb-2">
    <flux:dropdown position="bottom" align="start" class="w-full">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-zinc-200/60 dark:hover:bg-zinc-800"
        >
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-teal-500 text-sm font-semibold text-white">
                {{ str($current?->name ?? '?')->substr(0, 1)->upper() }}
            </span>
            <span class="grid flex-1 leading-tight">
                <span class="truncate text-sm font-semibold text-zinc-800 dark:text-white">{{ $current?->name }}</span>
                <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __('Workspace') }}</span>
            </span>
            <flux:icon.chevrons-up-down class="size-4 shrink-0 text-zinc-400" />
        </button>

        <flux:menu class="w-[240px]">
            <flux:menu.radio.group>
                @foreach ($workspaces as $workspace)
                    <flux:menu.item wire:click="switch({{ $workspace->id }})" :disabled="$workspace->id === $current?->id">
                        <span class="flex w-full items-center justify-between gap-2">
                            <span class="truncate">{{ $workspace->name }}</span>
                            @if ($workspace->id === $current?->id)
                                <flux:icon.check class="size-4 shrink-0" />
                            @endif
                        </span>
                    </flux:menu.item>
                @endforeach
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.item href="{{ route('workspaces.create') }}" icon="plus" wire:navigate>
                {{ __('Create workspace') }}
            </flux:menu.item>
            <flux:menu.item href="{{ route('settings.workspace') }}" icon="cog-6-tooth" wire:navigate>
                {{ __('Workspace settings') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
