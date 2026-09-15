<?php

use App\Actions\Workspaces\CreateWorkspace;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';

    public function create(CreateWorkspace $createWorkspace): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $createWorkspace->handle(Auth::user(), $validated['name']);

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<section class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <x-page-header
        :title="__('Create a workspace')"
        :description="__('Workspaces keep quizzes, responses and teammates together. You can have as many as you need.')"
        :back="route('dashboard')"
        :back-label="__('Dashboard')"
    />

    <x-panel :title="__('New workspace')" icon="building-office-2">
        <form wire:submit="create" class="flex flex-col gap-5">
            <flux:input
                wire:model="name"
                :label="__('Workspace name')"
                type="text"
                required
                autofocus
                :placeholder="__('e.g. Acme Marketing')"
                :description="__('You become its owner. Invite teammates once it exists.')"
            />

            <div class="flex items-center justify-end gap-3">
                <flux:button :href="route('dashboard')" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="plus">{{ __('Create workspace') }}</flux:button>
            </div>
        </form>
    </x-panel>
</section>
