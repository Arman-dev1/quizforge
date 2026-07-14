<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $deleteConfirmation = '';

    public function mount(): void
    {
        $this->name = $this->workspace()->name;
    }

    protected function workspace(): Workspace
    {
        return Auth::user()->currentWorkspace;
    }

    public function updateName(): void
    {
        $workspace = $this->workspace();

        $this->authorize('update', $workspace);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $workspace->update(['name' => $validated['name']]);

        $this->dispatch('workspace-updated');
    }

    public function leave(): void
    {
        $workspace = $this->workspace();
        $user = Auth::user();

        if ($workspace->isSoleOwner($user)) {
            $this->addError('leave', $workspace->members()->count() === 1
                ? __('You are the only member. Delete the workspace instead of leaving it.')
                : __('You are the only owner. Promote another member to owner before leaving.'));

            return;
        }

        $workspace->members()->detach($user->id);
        $user->forceFill(['current_workspace_id' => null])->save();

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function deleteWorkspace(): void
    {
        $workspace = $this->workspace();

        $this->authorize('delete', $workspace);

        if ($this->deleteConfirmation !== $workspace->name) {
            $this->addError('deleteConfirmation', __('The name you entered does not match the workspace name.'));

            return;
        }

        // Detach current-workspace pointers so members self-heal on next request.
        User::where('current_workspace_id', $workspace->id)
            ->update(['current_workspace_id' => null]);

        $workspace->delete();

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function with(): array
    {
        $workspace = $this->workspace();
        $role = Auth::user()->roleIn($workspace);

        return [
            'workspace' => $workspace,
            'canUpdate' => $role?->canManageWorkspace() ?? false,
            'canDelete' => Auth::user()->can('delete', $workspace),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="{{ __('Workspace') }}" subheading="{{ __('Manage your current workspace') }}">
        <form wire:submit="updateName" class="mt-6 space-y-6">
            <flux:input
                wire:model="name"
                label="{{ __('Workspace name') }}"
                type="text"
                required
                :disabled="! $canUpdate"
            />

            <flux:input
                value="{{ $workspace->slug }}"
                label="{{ __('Workspace URL slug') }}"
                type="text"
                disabled
            />

            @if ($canUpdate)
                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                    <x-action-message class="me-3" on="workspace-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            @endif
        </form>

        <flux:separator class="my-8" />

        <div class="space-y-4">
            <div>
                <flux:heading>{{ __('Leave workspace') }}</flux:heading>
                <flux:subheading>{{ __('You will lose access to everything in this workspace.') }}</flux:subheading>
            </div>

            @error('leave')
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            <flux:button
                variant="filled"
                wire:click="leave"
                wire:confirm="{{ __('Are you sure you want to leave this workspace?') }}"
            >
                {{ __('Leave workspace') }}
            </flux:button>
        </div>

        @if ($canDelete)
            <flux:separator class="my-8" />

            <div class="space-y-4">
                <div>
                    <flux:heading class="text-red-600 dark:text-red-400">{{ __('Danger zone') }}</flux:heading>
                    <flux:subheading>{{ __('Deleting a workspace removes all of its quizzes, responses, and leads.') }}</flux:subheading>
                </div>

                <flux:modal.trigger name="confirm-workspace-deletion">
                    <flux:button variant="danger">{{ __('Delete workspace') }}</flux:button>
                </flux:modal.trigger>

                <flux:modal name="confirm-workspace-deletion" focusable class="max-w-lg">
                    <form wire:submit="deleteWorkspace" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Delete this workspace?') }}</flux:heading>
                            <flux:subheading>
                                {{ __('This cannot be undone. Type the workspace name ":name" to confirm.', ['name' => $workspace->name]) }}
                            </flux:subheading>
                        </div>

                        <flux:input
                            wire:model="deleteConfirmation"
                            label="{{ __('Workspace name') }}"
                            type="text"
                        />

                        <div class="flex justify-end space-x-2">
                            <flux:modal.close>
                                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>

                            <flux:button variant="danger" type="submit">{{ __('Delete workspace') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            </div>
        @endif
    </x-settings.layout>
</section>
