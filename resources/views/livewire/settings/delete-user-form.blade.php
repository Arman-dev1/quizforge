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

{{-- Same danger-zone register as the workspace settings page. --}}
<section class="rounded-xl border border-red-200 bg-white dark:border-red-900/60 dark:bg-zinc-900">
    <header class="flex items-center gap-3 border-b border-red-200 px-5 py-4 dark:border-red-900/60">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400">
            <flux:icon.exclamation-triangle class="size-4.5" />
        </span>
        <div>
            <h2 class="text-sm font-bold text-red-700 dark:text-red-400">{{ __('Delete account') }}</h2>
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Permanent — this cannot be undone.') }}</p>
        </div>
    </header>

    <div class="p-5">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Deleting your account removes your profile and everything owned solely by you.') }}
        </p>

        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button class="mt-4" variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
                {{ __('Delete account') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="deleteUser" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Everything owned solely by this account is permanently deleted. Enter your password to confirm.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" id="password" :label="__('Password')" type="password" name="password" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
