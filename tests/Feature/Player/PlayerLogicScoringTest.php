<?php

namespace Tests\Feature\Player;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizResponse;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlayerLogicScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function ownerWithQuiz(array $quizSettings = []): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create(['settings' => $quizSettings]);

        return [$owner, $quiz];
    }

    /**
     * Page 1: yes/no trigger. Page 2: a follow-up shown only on "yes"
     * plus an always-visible question.
     */
    protected function publishLogicQuiz(): array
    {
        [$owner, $quiz] = $this->ownerWithQuiz();

        $pageOne = QuizPage::factory()->for($quiz)->create(['position' => 0]);
        $trigger = $pageOne->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::YesNo,
            'title' => 'Do you own a car?', 'is_required' => true, 'position' => 0,
        ]);

        $pageTwo = QuizPage::factory()->for($quiz)->create(['position' => 1]);
        $followUp = $pageTwo->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Which car brand?', 'is_required' => true, 'position' => 0,
            'logic' => ['match' => 'all', 'conditions' => [
                ['question_id' => $trigger->id, 'operator' => 'equals', 'value' => 'yes'],
            ]],
        ]);
        $always = $pageTwo->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Anything else?', 'position' => 1,
        ]);

        app(PublishQuiz::class)->handle($quiz, $owner);

        return [$quiz->refresh(), $trigger, $followUp, $always];
    }

    public function test_conditional_questions_appear_only_when_conditions_match(): void
    {
        [$quiz, $trigger] = $this->publishLogicQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$trigger->id}", 'yes')
            ->call('next')
            ->assertSee('Which car brand?')
            ->assertSee('Anything else?');
    }

    public function test_conditional_questions_stay_hidden_when_conditions_fail(): void
    {
        [$quiz, $trigger] = $this->publishLogicQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$trigger->id}", 'no')
            ->call('next')
            ->assertDontSee('Which car brand?')
            ->assertSee('Anything else?');
    }

    public function test_required_questions_hidden_by_logic_do_not_block_submission(): void
    {
        [$quiz, $trigger, $followUp] = $this->publishLogicQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$trigger->id}", 'no')
            ->call('next')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('completed', true);

        $response = QuizResponse::first();

        $this->assertSame(QuizResponse::STATUS_COMPLETED, $response->status);
        $this->assertSame(0, $response->answers()->where('question_id', $followUp->id)->count());
    }

    /**
     * A scored quiz: q1 worth 2 points, q2 worth 3 with penalty 1.
     */
    protected function publishScoredQuiz(array $results = []): array
    {
        [$owner, $quiz] = $this->ownerWithQuiz(array_merge(['scored' => true], $results !== [] ? ['results' => $results] : []));

        $page = QuizPage::factory()->for($quiz)->create();

        $questions = [];

        foreach ([['points' => 2, 'negative_points' => 0], ['points' => 3, 'negative_points' => 1]] as $index => $settings) {
            $question = $page->questions()->create([
                'quiz_id' => $quiz->id, 'type' => QuestionType::SingleChoice,
                'title' => 'Q'.($index + 1), 'is_required' => true,
                'position' => $index, 'settings' => $settings,
            ]);
            $question->options()->create(['label' => 'Right', 'position' => 0, 'is_correct' => true]);
            $question->options()->create(['label' => 'Wrong', 'position' => 1]);

            $questions[] = $question;
        }

        app(PublishQuiz::class)->handle($quiz, $owner);

        return [$quiz->refresh(), ...$questions];
    }

    public function test_completed_responses_are_scored_and_stamped(): void
    {
        [$quiz, $q1, $q2] = $this->publishScoredQuiz();

        $correct1 = $q1->options()->where('is_correct', true)->first()->id;
        $wrong2 = $q2->options()->where('is_correct', false)->first()->id;

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$q1->id}", $correct1)
            ->set("answers.{$q2->id}", $wrong2)
            ->call('next')
            ->assertSet('completed', true)
            ->assertSee('1')
            ->assertSee('20%');

        $response = QuizResponse::first();

        $this->assertSame(1, $response->score); // 2 - 1 penalty
        $this->assertSame(5, $response->max_score);
        $this->assertSame(20.0, (float) $response->percentage);
    }

    public function test_pass_and_grade_resolve_from_results_settings(): void
    {
        [$quiz, $q1, $q2] = $this->publishScoredQuiz([
            'show_score' => true,
            'pass_percentage' => 50,
            'grades' => [['min' => 80, 'label' => 'Gold'], ['min' => 0, 'label' => 'Bronze']],
            'thank_you_message' => 'Great effort!',
        ]);

        $correct1 = $q1->options()->where('is_correct', true)->first()->id;
        $correct2 = $q2->options()->where('is_correct', true)->first()->id;

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$q1->id}", $correct1)
            ->set("answers.{$q2->id}", $correct2)
            ->call('next')
            ->assertSee('Great effort!')
            ->assertSee(__('Passed'))
            ->assertSee('Gold');

        $response = QuizResponse::first();

        $this->assertTrue((bool) $response->passed);
        $this->assertSame('Gold', $response->grade);
        $this->assertSame(100.0, (float) $response->percentage);
    }

    public function test_score_is_hidden_when_show_score_is_off(): void
    {
        [$quiz, $q1, $q2] = $this->publishScoredQuiz(['show_score' => false]);

        $correct1 = $q1->options()->where('is_correct', true)->first()->id;
        $correct2 = $q2->options()->where('is_correct', true)->first()->id;

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$q1->id}", $correct1)
            ->set("answers.{$q2->id}", $correct2)
            ->call('next')
            ->assertSet('completed', true)
            ->assertDontSee('100%');

        // Still stamped on the response for the team.
        $this->assertSame(5, QuizResponse::first()->score);
    }

    public function test_results_settings_can_be_saved_from_the_quiz_page(): void
    {
        [$owner, $quiz] = $this->ownerWithQuiz();

        $this->actingAs($owner);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->set('resultScored', true)
            ->set('resultPassPercentage', '60')
            ->set('resultGrades', "80:Gold\n0:Bronze\nnot-a-band")
            ->set('resultMessage', 'Thanks a bunch!')
            ->set('resultRedirect', 'https://example.com/next')
            ->call('saveResults')
            ->assertHasNoErrors();

        $settings = $quiz->refresh()->settings;

        $this->assertTrue($settings['scored']);
        $this->assertEquals(60, $settings['results']['pass_percentage']);
        $this->assertCount(2, $settings['results']['grades']);
        $this->assertSame('Thanks a bunch!', $settings['results']['thank_you_message']);
        $this->assertSame('https://example.com/next', $settings['results']['redirect_url']);
    }
}
