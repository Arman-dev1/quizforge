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

<section class="mx-auto mt-10 w-full max-w-md space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Create a workspace') }}</flux:heading>
        <flux:subheading>{{ __('Workspaces keep quizzes, responses, and teammates together. You can create as many as you need.') }}</flux:subheading>
    </div>

    <form wire:submit="create" class="space-y-6">
        <flux:input
            wire:model="name"
            label="{{ __('Workspace name') }}"
            type="text"
            required
            autofocus
            placeholder="{{ __('e.g. Acme Marketing') }}"
        />

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('dashboard')" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit">{{ __('Create workspace') }}</flux:button>
        </div>
    </form>
</section>
