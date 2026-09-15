<?php

namespace Tests\Feature\Billing;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\QuizType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizResponse;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Billing\UsageLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function ownerInWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Owner)->create();
        $user->switchToWorkspace($workspace);

        return [$user, $workspace];
    }

    public function test_workspaces_default_to_the_free_plan(): void
    {
        [, $workspace] = $this->ownerInWorkspace();

        $this->assertSame('free', app(UsageLimits::class)->planKey($workspace));
    }

    public function test_an_active_subscription_resolves_the_paid_plan(): void
    {
        [, $workspace] = $this->ownerInWorkspace();

        config(['plans.pro.price_id' => 'pri_pro_test']);

        $subscription = $workspace->subscriptions()->create([
            'type' => 'default',
            'paddle_id' => 'sub_test',
            'status' => 'active',
        ]);
        $subscription->items()->create([
            'product_id' => 'pro_test',
            'price_id' => 'pri_pro_test',
            'status' => 'active',
            'quantity' => 1,
        ]);

        $this->assertSame('pro', app(UsageLimits::class)->planKey($workspace));
        $this->assertSame(20, app(UsageLimits::class)->limit($workspace, 'quizzes'));
    }

    public function test_quiz_creation_is_blocked_at_the_plan_limit(): void
    {
        config(['plans.free.limits.quizzes' => 1]);

        [$user, $workspace] = $this->ownerInWorkspace();
        Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('create', Quiz::class));

        // The create screen renders a friendly limit state, not a raw 403.
        $this->get(route('quizzes.create'))
            ->assertOk()
            ->assertSee(__('You have reached your plan limit'));

        // ...but the create action itself stays blocked.
        Volt::test('quizzes.create')
            ->set('name', 'Blocked quiz')
            ->set('type', QuizType::cases()[0]->value)
            ->call('create')
            ->assertForbidden();

        $this->assertSame(1, Quiz::withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->count());
    }

    public function test_duplication_also_counts_against_the_quiz_limit(): void
    {
        config(['plans.free.limits.quizzes' => 1]);

        [$user, $workspace] = $this->ownerInWorkspace();
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('duplicate')
            ->assertForbidden();

        $this->assertSame(1, Quiz::withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->count());
    }

    public function test_invites_are_blocked_when_seats_are_full(): void
    {
        config(['plans.free.limits.members' => 2]);

        [$user, $workspace] = $this->ownerInWorkspace();
        WorkspaceInvitation::factory()->for($workspace)->create();

        $this->actingAs($user);

        Volt::test('settings.members')
            ->set('email', 'new@example.com')
            ->set('role', 'editor')
            ->call('invite')
            ->assertHasErrors(['email']);

        $this->assertSame(1, $workspace->invitations()->count());
    }

    public function test_the_player_stops_accepting_responses_over_the_monthly_quota(): void
    {
        config(['plans.free.limits.responses_per_month' => 1]);

        [$owner, $workspace] = $this->ownerInWorkspace();

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();
        $page = QuizPage::factory()->for($quiz)->create();
        $page->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Say something', 'position' => 0,
        ]);
        app(PublishQuiz::class)->handle($quiz, $owner);

        QuizResponse::factory()->create([
            'quiz_id' => $quiz->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->get(route('quiz.play', $quiz->refresh()->slug))
            ->assertOk()
            ->assertSee(__('This quiz is no longer accepting responses.'))
            ->assertDontSee('Say something');

        $this->assertSame(1, QuizResponse::withoutGlobalScope('workspace')->count());
    }

    public function test_usage_summary_counts_pending_invites_as_seats(): void
    {
        [, $workspace] = $this->ownerInWorkspace();
        WorkspaceInvitation::factory()->for($workspace)->create();

        $usage = app(UsageLimits::class)->usage($workspace);

        $this->assertSame(2, $usage['members']['used']); // owner + pending invite
        $this->assertSame(1, $usage['members']['limit']); // free plan = just you
        $this->assertSame(0, $usage['quizzes']['used']);
    }
}
