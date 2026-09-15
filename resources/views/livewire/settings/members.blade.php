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

        $limits = app(\App\Services\Billing\UsageLimits::class);

        return [
            'workspace' => $workspace,
            'members' => $workspace->members()->orderBy('name')->get(),
            'invitations' => $workspace->invitations()->latest()->get(),
            'actorRole' => $actorRole,
            'canManage' => $actorRole->canManageMembers(),
            'assignableRoles' => $actorRole->assignableRoles(),
            'seatsUsed' => $limits->seatCount($workspace),
            'seatLimit' => $limits->limit($workspace, 'members'),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('Members')"
        :subheading="__('Who has access to :name, and what they can do.', ['name' => $workspace->name])"
        wide
    >
        @if ($canManage)
            <x-panel :title="__('Invite a teammate')" icon="user-plus" :description="__('They get an email link to join this workspace.')">
                <form wire:submit="invite" class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-48 flex-1">
                            <flux:input
                                wire:model="email"
                                :label="__('Email address')"
                                type="email"
                                placeholder="teammate@company.com"
                            />
                        </div>

                        <flux:select wire:model="role" :label="__('Role')" class="w-40">
                            @foreach ($assignableRoles as $assignableRole)
                                <option value="{{ $assignableRole->value }}">{{ $assignableRole->label() }}</option>
                            @endforeach
                        </flux:select>

                        <flux:button variant="primary" type="submit" icon="paper-airplane">{{ __('Send invite') }}</flux:button>
                    </div>

                    @if ($seatLimit !== null)
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 w-32 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div @class([
                                    'h-full rounded-full',
                                    'bg-red-500' => $seatsUsed >= $seatLimit,
                                    'bg-teal-600' => $seatsUsed < $seatLimit,
                                ]) style="width: {{ min(100, (int) round($seatsUsed / max($seatLimit, 1) * 100)) }}%"></div>
                            </div>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __(':used of :limit seats used', ['used' => $seatsUsed, 'limit' => $seatLimit]) }}
                                <span class="text-zinc-400 dark:text-zinc-500">{{ __('(members + pending invites)') }}</span>
                            </span>
                        </div>
                    @endif

                    <x-action-message on="member-invited">{{ __('Invitation sent.') }}</x-action-message>
                </form>
            </x-panel>
        @endif

        @error('members')
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        <x-panel :title="__('Members')" :description="trans_choice(':count person|:count people', $members->count(), ['count' => $members->count()])" flush>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($members as $member)
                    @php($memberRole = \App\Enums\WorkspaceRole::from($member->pivot->role))
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3.5" wire:key="member-{{ $member->id }}">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-teal-50 text-xs font-bold text-teal-700 dark:bg-teal-950/60 dark:text-teal-400">
                            {{ $member->initials() }}
                        </span>

                        <div class="min-w-0 flex-1 leading-tight">
                            <p class="truncate text-sm font-bold text-zinc-900 dark:text-white">
                                {{ $member->name }}
                                @if ($member->id === auth()->id())
                                    <span class="font-normal text-zinc-400">{{ __('· you') }}</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                        </div>

                        @if ($canManage && $actorRole->canAssign($memberRole) && $member->id !== auth()->id())
                            <flux:select
                                wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                class="w-36"
                                :aria-label="__('Role for :name', ['name' => $member->name])"
                            >
                                @foreach ($assignableRoles as $assignableRole)
                                    <option value="{{ $assignableRole->value }}" @selected($assignableRole === $memberRole)>
                                        {{ $assignableRole->label() }}
                                    </option>
                                @endforeach
                            </flux:select>

                            <x-confirm
                                :name="'remove-member-'.$member->id"
                                action="removeMember({{ $member->id }})"
                                :title="__('Remove :name?', ['name' => $member->name])"
                                :description="__('They lose access to this workspace immediately. You can re-invite them later.')"
                                :confirm="__('Remove member')"
                                icon="user-minus"
                            >
                                <x-slot:trigger>
                                    <flux:button variant="subtle" size="sm" icon="trash" :aria-label="__('Remove :name', ['name' => $member->name])" />
                                </x-slot:trigger>
                            </x-confirm>
                        @else
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-bold',
                                'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400' => $memberRole === \App\Enums\WorkspaceRole::Owner,
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $memberRole !== \App\Enums\WorkspaceRole::Owner,
                            ])>
                                {{ $memberRole->label() }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-panel>

        @if ($invitations->isNotEmpty() && $canManage)
            <x-panel
                :title="__('Pending invitations')"
                :description="trans_choice(':count invite awaiting a reply|:count invites awaiting a reply', $invitations->count(), ['count' => $invitations->count()])"
                flush
            >
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($invitations as $invitation)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3.5" wire:key="invitation-{{ $invitation->id }}">
                            <span @class([
                                'flex size-9 shrink-0 items-center justify-center rounded-full',
                                'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400' => ! $invitation->isExpired(),
                                'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => $invitation->isExpired(),
                            ])>
                                <flux:icon.envelope class="size-4" />
                            </span>

                            <div class="min-w-0 flex-1 leading-tight">
                                <p class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $invitation->email }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $invitation->role->label() }}
                                    &middot;
                                    @if ($invitation->isExpired())
                                        <span class="font-semibold text-red-600 dark:text-red-400">{{ __('Expired — resend to reopen it') }}</span>
                                    @else
                                        {{ __('Expires :date', ['date' => $invitation->expires_at->diffForHumans()]) }}
                                    @endif
                                </p>
                            </div>

                            <flux:button variant="filled" size="sm" wire:click="resendInvitation({{ $invitation->id }})">
                                {{ __('Resend') }}
                            </flux:button>

                            <x-confirm
                                :name="'revoke-inv-'.$invitation->id"
                                action="revokeInvitation({{ $invitation->id }})"
                                :title="__('Revoke this invitation?')"
                                :description="__('The invite link for :email will stop working.', ['email' => $invitation->email])"
                                :confirm="__('Revoke')"
                                icon="x-mark"
                            >
                                <x-slot:trigger>
                                    <flux:button variant="subtle" size="sm" icon="trash" :aria-label="__('Revoke invitation for :email', ['email' => $invitation->email])" />
                                </x-slot:trigger>
                            </x-confirm>
                        </li>
                    @endforeach
                </ul>
            </x-panel>

            <x-action-message on="invitation-resent">{{ __('Invitation resent.') }}</x-action-message>
        @endif
    </x-settings.layout>
</section>
