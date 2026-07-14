<?php

use App\Enums\WorkspaceRole;
use App\Livewire\Actions\Logout;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = Auth::user();

        // Workspaces where this user is the only owner but other members remain
        // would be orphaned — block deletion until ownership is transferred.
        $blocking = $user->workspaces()
            ->wherePivot('role', WorkspaceRole::Owner->value)
            ->get()
            ->filter(fn (Workspace $workspace) => $workspace->owners()->count() === 1
                && $workspace->members()->count() > 1);

        if ($blocking->isNotEmpty()) {
            $this->addError('password', __('You are the only owner of :names. Transfer ownership or remove the other members first.', [
                'names' => $blocking->pluck('name')->join(', '),
            ]));

            return;
        }

        // Workspaces where they are the only member go with them.
        $user->workspaces()
            ->get()
            ->filter(fn (Workspace $workspace) => $workspace->members()->count() === 1)
            ->each
            ->delete();

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete Account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete Account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" id="password" label="{{ __('Password') }}" type="password" name="password" />

            <div class="flex justify-end space-x-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Delete Account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
