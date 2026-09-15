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

    <x-settings.layout
        :heading="__('Workspace')"
        :subheading="__('Name, address, and who can leave or delete it.')"
    >
        <x-panel :title="__('General')" icon="cog-6-tooth">
            <form wire:submit="updateName" class="flex flex-col gap-5">
                <flux:input
                    wire:model="name"
                    :label="__('Workspace name')"
                    type="text"
                    required
                    :disabled="! $canUpdate"
                    :description="$canUpdate ? __('Shown in the sidebar and on invitations.') : __('Only owners and admins can rename this workspace.')"
                />

                <flux:input
                    value="{{ $workspace->slug }}"
                    :label="__('URL slug')"
                    type="text"
                    disabled
                    :description="__('Generated from the name when the workspace was created.')"
                />

                @if ($canUpdate)
                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                        <x-action-message on="workspace-updated">{{ __('Saved.') }}</x-action-message>
                    </div>
                @endif
            </form>
        </x-panel>

        <x-panel :title="__('Leave workspace')" icon="arrow-right-start-on-rectangle">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('You lose access to every quiz, response and lead here. An owner would need to re-invite you.') }}
            </p>

            @error('leave')
                <flux:text class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            <x-confirm
                action="leave"
                :title="__('Leave this workspace?')"
                :description="__('You will lose access to everything in this workspace. An owner would need to re-invite you.')"
                :confirm="__('Leave workspace')"
                icon="arrow-right-start-on-rectangle"
            >
                <x-slot:trigger>
                    <flux:button class="mt-4" variant="filled">{{ __('Leave workspace') }}</flux:button>
                </x-slot:trigger>
            </x-confirm>
        </x-panel>

        @if ($canDelete)
            {{-- Destructive actions get their own visual register so they are
                 never mistaken for the settings above them. --}}
            <section class="rounded-xl border border-red-200 bg-white dark:border-red-900/60 dark:bg-zinc-900">
                <header class="flex items-center gap-3 border-b border-red-200 px-5 py-4 dark:border-red-900/60">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400">
                        <flux:icon.exclamation-triangle class="size-4.5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-red-700 dark:text-red-400">{{ __('Danger zone') }}</h2>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Irreversible actions.') }}</p>
                    </div>
                </header>

                <div class="p-5">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Deleting :name removes all of its quizzes, responses and leads for everyone.', ['name' => $workspace->name]) }}
                    </p>

                    <flux:modal.trigger name="confirm-workspace-deletion">
                        <flux:button class="mt-4" variant="danger">{{ __('Delete workspace') }}</flux:button>
                    </flux:modal.trigger>

                    <flux:modal name="confirm-workspace-deletion" focusable class="max-w-lg">
                        <form wire:submit="deleteWorkspace" class="flex flex-col gap-6">
                            <div>
                                <flux:heading size="lg">{{ __('Delete this workspace?') }}</flux:heading>
                                <flux:subheading>
                                    {{ __('This cannot be undone. Type the workspace name ":name" to confirm.', ['name' => $workspace->name]) }}
                                </flux:subheading>
                            </div>

                            <flux:input
                                wire:model="deleteConfirmation"
                                :label="__('Workspace name')"
                                type="text"
                                :placeholder="$workspace->name"
                            />

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>

                                <flux:button variant="danger" type="submit">{{ __('Delete workspace') }}</flux:button>
                            </div>
                        </form>
                    </flux:modal>
                </div>
            </section>
        @endif
    </x-settings.layout>
</section>
