<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function adminInWorkspace(): array
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->withMember($admin, WorkspaceRole::Admin)->create();
        $admin->switchToWorkspace($workspace);

        return [$admin, $workspace];
    }

    public function test_admins_can_invite_people_by_email(): void
    {
        Mail::fake();

        [$admin, $workspace] = $this->adminInWorkspace();

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->set('email', 'new@example.com')
            ->set('role', 'editor')
            ->call('invite')
            ->assertHasNoErrors();

        $invitation = $workspace->invitations()->where('email', 'new@example.com')->first();

        $this->assertNotNull($invitation);
        $this->assertSame(WorkspaceRole::Editor, $invitation->role);

        Mail::assertQueued(WorkspaceInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
    }

    public function test_existing_members_cannot_be_invited(): void
    {
        Mail::fake();

        [$admin, $workspace] = $this->adminInWorkspace();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Viewer->value]);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->set('email', 'member@example.com')
            ->set('role', 'editor')
            ->call('invite')
            ->assertHasErrors(['email']);

        Mail::assertNothingQueued();
    }

    public function test_duplicate_pending_invitations_are_rejected(): void
    {
        Mail::fake();

        [$admin, $workspace] = $this->adminInWorkspace();
        WorkspaceInvitation::factory()->for($workspace)->create(['email' => 'new@example.com']);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->set('email', 'new@example.com')
            ->set('role', 'editor')
            ->call('invite')
            ->assertHasErrors(['email']);
    }

    public function test_inviting_over_an_expired_invitation_replaces_it(): void
    {
        Mail::fake();

        [$admin, $workspace] = $this->adminInWorkspace();
        $expired = WorkspaceInvitation::factory()->for($workspace)->expired()->create(['email' => 'new@example.com']);

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->set('email', 'new@example.com')
            ->set('role', 'editor')
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertNull($expired->fresh());
        $this->assertSame(1, $workspace->invitations()->count());
    }

    public function test_invitees_can_accept_an_invitation(): void
    {
        [$admin, $workspace] = $this->adminInWorkspace();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = WorkspaceInvitation::factory()->for($workspace)->create([
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::Editor,
        ]);

        $this->actingAs($invitee);

        Volt::test('invitations.accept', ['token' => $invitation->token])
            ->call('accept')
            ->assertRedirect(route('dashboard'));

        $this->assertSame(WorkspaceRole::Editor, $invitee->roleIn($workspace));
        $this->assertSame($workspace->id, $invitee->refresh()->current_workspace_id);
        $this->assertNull($invitation->fresh());
    }

    public function test_expired_invitations_cannot_be_accepted(): void
    {
        [$admin, $workspace] = $this->adminInWorkspace();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = WorkspaceInvitation::factory()->for($workspace)->expired()->create([
            'email' => 'invitee@example.com',
        ]);

        $this->actingAs($invitee);

        Volt::test('invitations.accept', ['token' => $invitation->token])
            ->assertSet('status', 'expired')
            ->call('accept');

        $this->assertFalse($workspace->hasMember($invitee));
    }

    public function test_invitations_cannot_be_accepted_by_a_different_email(): void
    {
        [$admin, $workspace] = $this->adminInWorkspace();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $invitation = WorkspaceInvitation::factory()->for($workspace)->create([
            'email' => 'invitee@example.com',
        ]);

        $this->actingAs($stranger);

        Volt::test('invitations.accept', ['token' => $invitation->token])
            ->assertSet('status', 'mismatch')
            ->call('accept');

        $this->assertFalse($workspace->hasMember($stranger));
        $this->assertNotNull($invitation->fresh());
    }

    public function test_guests_are_redirected_to_login_when_opening_an_invitation(): void
    {
        $invitation = WorkspaceInvitation::factory()->create();

        $this->get(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('login'));
    }

    public function test_invitations_can_be_revoked(): void
    {
        [$admin, $workspace] = $this->adminInWorkspace();
        $invitation = WorkspaceInvitation::factory()->for($workspace)->create();

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('revokeInvitation', $invitation->id)
            ->assertHasNoErrors();

        $this->assertNull($invitation->fresh());
    }

    public function test_resending_regenerates_the_token_and_expiry(): void
    {
        Mail::fake();

        [$admin, $workspace] = $this->adminInWorkspace();
        $invitation = WorkspaceInvitation::factory()->for($workspace)->create([
            'expires_at' => now()->addDay(),
        ]);
        $originalToken = $invitation->token;

        $this->actingAs($admin);

        Volt::test('settings.members')
            ->call('resendInvitation', $invitation->id)
            ->assertHasNoErrors();

        $invitation->refresh();

        $this->assertNotSame($originalToken, $invitation->token);
        $this->assertTrue($invitation->expires_at->greaterThan(now()->addDays(6)));

        Mail::assertQueued(WorkspaceInvitationMail::class);
    }
}
