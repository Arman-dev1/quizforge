<?php

use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $email = '';
    public string $role = 'editor';

    protected function workspace(): Workspace
    {
        return Auth::user()->currentWorkspace;
    }

    protected ?WorkspaceRole $resolvedRole = null;

    protected function actorRole(): WorkspaceRole
    {
        return $this->resolvedRole ??= Auth::user()->roleIn($this->workspace());
    }

    public function invite(): void
    {
        $workspace = $this->workspace();

        $this->authorize('manageMembers', $workspace);

        $assignable = array_map(fn (WorkspaceRole $r) => $r->value, $this->actorRole()->assignableRoles());

        $validated = $this->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'role' => ['required', Rule::in($assignable)],
        ]);

        if (! app(\App\Services\Billing\UsageLimits::class)->canAddMember($workspace)) {
            $this->addError('email', __('You have reached your plan\'s member limit. Upgrade to invite more people.'));

            return;
        }

        if ($workspace->members()->where('email', $validated['email'])->exists()) {
            $this->addError('email', __('This person is already a member of the workspace.'));

            return;
        }

        $existing = $workspace->invitations()->where('email', $validated['email'])->first();

        if ($existing && ! $existing->isExpired()) {
            $this->addError('email', __('This person already has a pending invitation. You can resend it below.'));

            return;
        }

        $existing?->delete();

        $invitation = $workspace->invitations()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => WorkspaceInvitation::generateToken(),
            'invited_by' => Auth::id(),
            'expires_at' => WorkspaceInvitation::defaultExpiry(),
        ]);

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation));

        $this->reset('email');
        $this->role = WorkspaceRole::Editor->value;
        $this->dispatch('member-invited');
    }

    public function updateRole(int $userId, string $role): void
    {
        $workspace = $this->workspace();

        $this->authorize('manageMembers', $workspace);

        $newRole = WorkspaceRole::tryFrom($role);
        $target = $workspace->members()->whereKey($userId)->firstOrFail();
        $currentRole = WorkspaceRole::from($target->pivot->role);

        if (! $newRole || ! $this->actorRole()->canAssign($newRole) || ! $this->actorRole()->canAssign($currentRole)) {
            $this->addError('members', __('You are not allowed to make that role change.'));

            return;
        }

        if ($currentRole === WorkspaceRole::Owner && $newRole !== WorkspaceRole::Owner && $workspace->owners()->count() === 1) {
            $this->addError('members', __('A workspace needs at least one owner. Promote someone else first.'));

            return;
        }

        $workspace->members()->updateExistingPivot($userId, ['role' => $newRole->value]);
    }

    public function removeMember(int $userId): void
    {
        $workspace = $this->workspace();

        $this->authorize('manageMembers', $workspace);

        if ($userId === Auth::id()) {
            $this->addError('members', __('You cannot remove yourself. Use "Leave workspace" in workspace settings instead.'));

            return;
        }

        $target = $workspace->members()->whereKey($userId)->firstOrFail();
        $targetRole = WorkspaceRole::from($target->pivot->role);

        if (! $this->actorRole()->canAssign($targetRole)) {
            $this->addError('members', __('You are not allowed to remove this member.'));

            return;
        }

        $workspace->members()->detach($userId);

        // Their next request self-heals to another workspace.
        User::whereKey($userId)
            ->where('current_workspace_id', $workspace->id)
            ->update(['current_workspace_id' => null]);
    }

    public function resendInvitation(int $invitationId): void
    {
        $workspace = $this->workspace();

        $this->authorize('manageMembers', $workspace);

        $invitation = $workspace->invitations()->findOrFail($invitationId);

        $invitation->update([
            'token' => WorkspaceInvitation::generateToken(),
            'expires_at' => WorkspaceInvitation::defaultExpiry(),
        ]);

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation));

        $this->dispatch('invitation-resent');
    }

    public function revokeInvitation(int $invitationId): void
    {
        $workspace = $this->workspace();

        $this->authorize('manageMembers', $workspace);

        $workspace->invitations()->findOrFail($invitationId)->delete();
    }

    public function with(): array
    {
        $workspace = $this->workspace();
        $actorRole = $this->actorRole();

        return [
            'workspace' => $workspace,
            'members' => $workspace->members()->orderBy('name')->get(),
            'invitations' => $workspace->invitations()->latest()->get(),
            'actorRole' => $actorRole,
            'canManage' => $actorRole->canManageMembers(),
            'assignableRoles' => $actorRole->assignableRoles(),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="{{ __('Members') }}" :subheading="__('Manage who has access to :name', ['name' => $workspace->name])">
        @if ($canManage)
            <form wire:submit="invite" class="mt-6 space-y-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-48 flex-1">
                        <flux:input
                            wire:model="email"
                            label="{{ __('Invite by email') }}"
                            type="email"
                            placeholder="teammate@company.com"
                        />
                    </div>

                    <flux:select wire:model="role" label="{{ __('Role') }}" class="w-36">
                        @foreach ($assignableRoles as $assignableRole)
                            <option value="{{ $assignableRole->value }}">{{ $assignableRole->label() }}</option>
                        @endforeach
                    </flux:select>

                    <flux:button variant="primary" type="submit">{{ __('Invite') }}</flux:button>
                </div>

                <x-action-message on="member-invited">
                    {{ __('Invitation sent.') }}
                </x-action-message>
            </form>
        @endif

        @error('members')
            <flux:text class="mt-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        <div class="mt-8">
            <flux:heading>{{ __('Members') }} ({{ $members->count() }})</flux:heading>

            <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($members as $member)
                    @php($memberRole = \App\Enums\WorkspaceRole::from($member->pivot->role))
                    <li class="flex items-center gap-3 p-3" wire:key="member-{{ $member->id }}">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-neutral-200 text-sm font-medium text-black dark:bg-neutral-700 dark:text-white">
                            {{ $member->initials() }}
                        </span>

                        <div class="min-w-0 flex-1 leading-tight">
                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-white">
                                {{ $member->name }}
                                @if ($member->id === auth()->id())
                                    <span class="text-xs text-zinc-500">({{ __('you') }})</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                        </div>

                        @if ($canManage && $actorRole->canAssign($memberRole) && $member->id !== auth()->id())
                            <flux:select
                                wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                class="w-32"
                                aria-label="{{ __('Role for :name', ['name' => $member->name]) }}"
                            >
                                @foreach ($assignableRoles as $assignableRole)
                                    <option value="{{ $assignableRole->value }}" @selected($assignableRole === $memberRole)>
                                        {{ $assignableRole->label() }}
                                    </option>
                                @endforeach
                            </flux:select>

                            <flux:button
                                variant="subtle"
                                size="sm"
                                icon="trash"
                                wire:click="removeMember({{ $member->id }})"
                                wire:confirm="{{ __('Remove :name from this workspace?', ['name' => $member->name]) }}"
                                aria-label="{{ __('Remove :name', ['name' => $member->name]) }}"
                            />
                        @else
                            <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                {{ $memberRole->label() }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($invitations->isNotEmpty() && $canManage)
            <div class="mt-8">
                <flux:heading>{{ __('Pending invitations') }} ({{ $invitations->count() }})</flux:heading>

                <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($invitations as $invitation)
                        <li class="flex items-center gap-3 p-3" wire:key="invitation-{{ $invitation->id }}">
                            <div class="min-w-0 flex-1 leading-tight">
                                <p class="truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $invitation->email }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $invitation->role->label() }}
                                    &middot;
                                    @if ($invitation->isExpired())
                                        <span class="text-red-600 dark:text-red-400">{{ __('Expired') }}</span>
                                    @else
                                        {{ __('Expires :date', ['date' => $invitation->expires_at->diffForHumans()]) }}
                                    @endif
                                </p>
                            </div>

                            <flux:button variant="subtle" size="sm" wire:click="resendInvitation({{ $invitation->id }})">
                                {{ __('Resend') }}
                            </flux:button>

                            <flux:button
                                variant="subtle"
                                size="sm"
                                icon="trash"
                                wire:click="revokeInvitation({{ $invitation->id }})"
                                wire:confirm="{{ __('Revoke this invitation?') }}"
                                aria-label="{{ __('Revoke invitation for :email', ['email' => $invitation->email]) }}"
                            />
                        </li>
                    @endforeach
                </ul>

                <x-action-message class="mt-2" on="invitation-resent">
                    {{ __('Invitation resent.') }}
                </x-action-message>
            </div>
        @endif
    </x-settings.layout>
</section>
