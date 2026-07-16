<?php

use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public ?WorkspaceInvitation $invitation = null;
    public string $status = 'ok';

    public function mount(string $token): void
    {
        $this->invitation = WorkspaceInvitation::with(['workspace', 'inviter'])
            ->where('token', $token)
            ->first();

        $this->status = $this->determineStatus();

        // Already a member: nothing to accept, just clean up and go.
        if ($this->status === 'ok' && $this->invitation->workspace->hasMember(Auth::user())) {
            $this->invitation->delete();
            Auth::user()->switchToWorkspace($this->invitation->workspace);
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    protected function determineStatus(): string
    {
        if (! $this->invitation || ! $this->invitation->workspace) {
            return 'invalid';
        }

        if ($this->invitation->isExpired()) {
            return 'expired';
        }

        if (strcasecmp($this->invitation->email, Auth::user()->email) !== 0) {
            return 'mismatch';
        }

        return 'ok';
    }

    public function accept(): void
    {
        if ($this->determineStatus() !== 'ok') {
            $this->status = $this->determineStatus();

            return;
        }

        $workspace = $this->invitation->workspace;

        if (! $workspace->hasMember(Auth::user())) {
            $workspace->members()->attach(Auth::id(), ['role' => $this->invitation->role->value]);

            $managers = $workspace->members()->get()->filter(
                fn ($member) => $member->id !== Auth::id()
                    && \App\Enums\WorkspaceRole::from($member->pivot->role)->canManageMembers(),
            );

            \Illuminate\Support\Facades\Notification::send(
                $managers,
                new \App\Notifications\MemberJoined(Auth::user(), $workspace),
            );
        }

        $this->invitation->delete();

        Auth::user()->switchToWorkspace($workspace);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function decline(): void
    {
        if ($this->invitation) {
            $this->invitation->delete();
        }

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($status === 'invalid')
        <x-auth-header title="{{ __('Invitation not found') }}" description="{{ __('This invitation link is invalid or has already been used.') }}" />

        <flux:button :href="route('dashboard')" wire:navigate variant="primary">{{ __('Go to dashboard') }}</flux:button>
    @elseif ($status === 'expired')
        <x-auth-header title="{{ __('Invitation expired') }}" description="{{ __('This invitation has expired. Ask a workspace admin to send you a new one.') }}" />

        <flux:button :href="route('dashboard')" wire:navigate variant="primary">{{ __('Go to dashboard') }}</flux:button>
    @elseif ($status === 'mismatch')
        <x-auth-header
            title="{{ __('Wrong account') }}"
            description="{{ __('This invitation was sent to :email, but you are signed in as :current. Sign in with the invited email address to accept it.', ['email' => $invitation->email, 'current' => auth()->user()->email]) }}"
        />

        <flux:button :href="route('dashboard')" wire:navigate variant="primary">{{ __('Go to dashboard') }}</flux:button>
    @else
        <x-auth-header
            title="{{ __('Join :workspace', ['workspace' => $invitation->workspace->name]) }}"
            description="{{ __(':inviter invited you to join as :role.', ['inviter' => $invitation->inviter?->name ?? __('A teammate'), 'role' => $invitation->role->label()]) }}"
        />

        <div class="flex items-center justify-center gap-3">
            <flux:button wire:click="decline" variant="filled">{{ __('Decline') }}</flux:button>
            <flux:button wire:click="accept" variant="primary">{{ __('Accept invitation') }}</flux:button>
        </div>
    @endif
</div>
