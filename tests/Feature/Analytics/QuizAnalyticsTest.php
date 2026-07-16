<?php

namespace Tests\Feature\Analytics;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizResponse;
use App\Models\QuizView;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\QuizAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuizAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published two-page quiz (single choice on page 1, short text on
     * page 2) with mixed responses.
     */
    protected function publishedQuizWithData(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Editor)->create();
        $user->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $pageOne = QuizPage::factory()->for($quiz)->create(['position' => 0, 'title' => 'Choice page']);
        $choice = $pageOne->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::SingleChoice,
            'title' => 'Pick one', 'position' => 0,
        ]);
        $red = $choice->options()->create(['label' => 'Red', 'position' => 0, 'is_correct' => true]);
        $blue = $choice->options()->create(['label' => 'Blue', 'position' => 1]);

        $pageTwo = QuizPage::factory()->for($quiz)->create(['position' => 1, 'title' => 'Text page']);
        $pageTwo->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Comments', 'position' => 0,
        ]);

        app(PublishQuiz::class)->handle($quiz, $user);
        $version = $quiz->refresh()->latestVersion();

        // Two completed (Red + Blue), one partial stuck on page 0.
        foreach ([[$red, true], [$blue, true], [null, false]] as [$option, $completed]) {
            $factory = QuizResponse::factory();
            $response = ($completed ? $factory->completed() : $factory)->create([
                'quiz_id' => $quiz->id,
                'workspace_id' => $workspace->id,
                'quiz_version_id' => $version->id,
                'current_page' => $completed ? 1 : 0,
                'started_at' => now()->subMinutes(5),
                'completed_at' => $completed ? now()->subMinutes(3) : null,
            ]);

            if ($option !== null) {
                $response->answers()->create([
                    'question_id' => $choice->id,
                    'question_type' => 'single_choice',
                    'value' => $option->id,
                ]);
            }
        }

        return [$user, $quiz];
    }

    public function test_opening_the_player_records_a_daily_view(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();

        Volt::test('play', ['slug' => $quiz->slug]);
        Volt::test('play', ['slug' => $quiz->slug]);

        $this->assertSame(1, QuizView::where('quiz_id', $quiz->id)->count());
        $this->assertSame(2, (int) $quiz->views()->sum('views'));
    }

    public function test_closed_quizzes_do_not_record_views(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();
        $quiz->update(['status' => QuizStatus::Closed]);

        Volt::test('play', ['slug' => $quiz->slug]);

        $this->assertSame(0, (int) $quiz->views()->sum('views'));
    }

    public function test_summary_computes_starts_completions_rate_and_duration(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();
        QuizView::record($quiz);

        $summary = app(QuizAnalytics::class)->summary($quiz);

        $this->assertSame(1, $summary['views']);
        $this->assertSame(3, $summary['starts']);
        $this->assertSame(2, $summary['completions']);
        $this->assertSame(67, $summary['completion_rate']);
        $this->assertSame(120, $summary['avg_seconds']);
    }

    public function test_funnel_counts_how_many_reached_each_page(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();

        $funnel = app(QuizAnalytics::class)->pageFunnel($quiz);

        $this->assertCount(2, $funnel);
        $this->assertSame('Choice page', $funnel[0]['title']);
        $this->assertSame(3, $funnel[0]['reached']);
        $this->assertSame(100, $funnel[0]['rate']);
        $this->assertSame(2, $funnel[1]['reached']);
        $this->assertSame(67, $funnel[1]['rate']);
    }

    public function test_question_stats_include_option_distributions(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();

        $stats = app(QuizAnalytics::class)->questionStats($quiz);

        $this->assertSame('Pick one', $stats[0]['title']);
        $this->assertSame(2, $stats[0]['answered']);

        $red = collect($stats[0]['distribution'])->firstWhere('label', 'Red');

        $this->assertSame(1, $red['count']);
        $this->assertSame(50, $red['pct']);
        $this->assertTrue($red['is_correct']);

        $this->assertSame(0, $stats[1]['answered']);
    }

    public function test_trashed_responses_are_excluded_from_question_stats(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();

        $quiz->responses()->where('status', QuizResponse::STATUS_COMPLETED)->first()->delete();

        $stats = app(QuizAnalytics::class)->questionStats($quiz);

        $this->assertSame(1, $stats[0]['answered']);
    }

    public function test_the_analytics_page_renders_for_members(): void
    {
        [$user, $quiz] = $this->publishedQuizWithData();

        $this->actingAs($user)
            ->get(route('quizzes.analytics', $quiz))
            ->assertOk()
            ->assertSee(__('Completion rate'))
            ->assertSee('Pick one')
            ->assertSee('Red');
    }

    public function test_unpublished_quizzes_show_an_empty_state(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Viewer)->create();
        $user->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user)
            ->get(route('quizzes.analytics', $quiz))
            ->assertOk()
            ->assertSee(__('No data yet'));
    }

    public function test_analytics_are_not_reachable_across_workspaces(): void
    {
        [$user] = $this->publishedQuizWithData();

        $foreign = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.analytics', $foreign))
            ->assertNotFound();
    }
}
