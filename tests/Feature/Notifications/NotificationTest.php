<?php

namespace Tests\Feature\Notifications;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\MemberJoined;
use App\Notifications\NewLead;
use App\Notifications\NewResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published two-page quiz: email on page 1, short text on page 2.
     */
    protected function publishedQuiz(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $pageOne = QuizPage::factory()->for($quiz)->create(['position' => 0]);
        $email = $pageOne->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::Email,
            'title' => 'Your email', 'is_required' => true, 'position' => 0,
        ]);

        $pageTwo = QuizPage::factory()->for($quiz)->create(['position' => 1]);
        $text = $pageTwo->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Anything else?', 'position' => 0,
        ]);

        app(PublishQuiz::class)->handle($quiz, $owner);

        return [$owner, $quiz->refresh(), $email, $text];
    }

    public function test_completing_a_quiz_notifies_workspace_members_in_app_only_by_default(): void
    {
        Notification::fake();

        [$owner, $quiz, $email] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next')
            ->call('next');

        Notification::assertSentTo($owner, NewResponse::class, function (NewResponse $notification) use ($owner) {
            return $notification->via($owner) === ['database'];
        });
    }

    public function test_lead_capture_notifies_exactly_once_per_response(): void
    {
        Notification::fake();

        [$owner, $quiz, $email, $text] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next')
            ->set("answers.{$text->id}", 'All good')
            ->call('next');

        Notification::assertSentToTimes($owner, NewLead::class, 1);

        Notification::assertSentTo($owner, NewLead::class, function (NewLead $notification) use ($owner) {
            return $notification->email === 'ada@example.com'
                && in_array('mail', $notification->via($owner), true);
        });
    }

    public function test_mail_channel_follows_user_preferences(): void
    {
        Notification::fake();

        [$owner, $quiz, $email] = $this->publishedQuiz();

        $owner->forceFill(['notification_preferences' => [
            'new_response' => ['database' => true, 'mail' => true],
            'new_lead' => ['database' => false, 'mail' => false],
        ]])->save();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next')
            ->call('next');

        Notification::assertSentTo($owner->fresh(), NewResponse::class, function (NewResponse $notification) use ($owner) {
            return $notification->via($owner->fresh()) === ['database', 'mail'];
        });

        // All channels off means the notification is never sent at all.
        Notification::assertNotSentTo($owner->fresh(), NewLead::class);
    }

    public function test_accepting_an_invitation_notifies_managers_but_not_the_joiner(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()
            ->withMember($owner, WorkspaceRole::Owner)
            ->withMember($editor, WorkspaceRole::Editor)
            ->create();

        $invitee = User::factory()->create(['email' => 'newbie@example.com']);
        $invitation = WorkspaceInvitation::factory()->for($workspace)->create(['email' => 'newbie@example.com']);

        $this->actingAs($invitee);

        Volt::test('invitations.accept', ['token' => $invitation->token])
            ->call('accept');

        Notification::assertSentTo($owner, MemberJoined::class);
        Notification::assertNotSentTo($invitee, MemberJoined::class);
        Notification::assertNotSentTo($editor, MemberJoined::class);
    }

    public function test_notification_preferences_can_be_saved(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create();
        $user->switchToWorkspace($workspace);

        $this->actingAs($user);

        Volt::test('settings.notifications')
            ->set('preferences.new_lead.mail', false)
            ->set('preferences.new_response.mail', true)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertFalse($user->wantsNotification('new_lead', 'mail'));
        $this->assertTrue($user->wantsNotification('new_response', 'mail'));
        $this->assertTrue($user->wantsNotification('new_response', 'database'));
    }

    public function test_the_bell_lists_notifications_and_marks_them_read(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create();
        $user->switchToWorkspace($workspace);

        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => NewResponse::class,
            'data' => ['type' => 'new_response', 'icon' => 'inbox', 'message' => 'New response on "Demo"', 'url' => '/dashboard'],
        ]);

        $this->actingAs($user);

        $component = Volt::test('notification-bell')
            ->assertSee('New response on');

        $this->assertSame(1, $user->unreadNotifications()->count());

        $component->call('markAllRead');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create();
        $user->switchToWorkspace($workspace);

        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => NewResponse::class,
            'data' => ['message' => 'Ping', 'url' => '/quizzes'],
        ]);

        $this->actingAs($user);

        Volt::test('notification-bell')
            ->call('open', $notification->id)
            ->assertRedirect('/quizzes');

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
