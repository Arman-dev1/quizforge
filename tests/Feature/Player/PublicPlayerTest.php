<?php

namespace Tests\Feature\Player;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizResponse;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PublicPlayerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published two-page quiz: page 1 has a required name + email,
     * page 2 a required single choice.
     */
    protected function publishedQuiz(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $pageOne = QuizPage::factory()->for($quiz)->create(['position' => 0, 'title' => 'About you']);
        $name = $pageOne->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Your name', 'is_required' => true, 'position' => 0,
        ]);
        $email = $pageOne->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::Email,
            'title' => 'Your email', 'is_required' => true, 'position' => 1,
        ]);

        $pageTwo = QuizPage::factory()->for($quiz)->create(['position' => 1, 'title' => 'Preferences']);
        $choice = $pageTwo->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::SingleChoice,
            'title' => 'Pick a plan', 'is_required' => true, 'position' => 0,
        ]);
        foreach (['Starter', 'Pro', 'Scale'] as $index => $label) {
            $choice->options()->create(['label' => $label, 'position' => $index]);
        }

        app(PublishQuiz::class)->handle($quiz, $owner);

        return [$quiz->refresh(), $name, $email, $choice];
    }

    public function test_guests_can_open_a_published_quiz(): void
    {
        [$quiz] = $this->publishedQuiz();

        $this->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertSee($quiz->name)
            ->assertSee('Your name');
    }

    public function test_draft_and_archived_quizzes_are_not_public(): void
    {
        $draft = Quiz::factory()->create();
        $archived = Quiz::factory()->archived()->create();

        $this->get(route('quiz.play', $draft->slug))->assertNotFound();
        $this->get(route('quiz.play', $archived->slug))->assertNotFound();
    }

    public function test_closed_quizzes_show_a_closed_notice(): void
    {
        [$quiz] = $this->publishedQuiz();
        $quiz->update(['status' => QuizStatus::Closed]);

        $this->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertSee(__('This quiz is no longer accepting responses.'));
    }

    public function test_members_of_other_workspaces_can_play_published_quizzes(): void
    {
        [$quiz] = $this->publishedQuiz();

        $stranger = User::factory()->create();
        Workspace::factory()->withMember($stranger, WorkspaceRole::Owner)->create();

        $this->actingAs($stranger)
            ->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertSee('Your name');
    }

    public function test_edits_after_publishing_stay_private_until_republished(): void
    {
        [$quiz, $name] = $this->publishedQuiz();

        $name->update(['title' => 'Full legal name']);

        $this->get(route('quiz.play', $quiz->slug))
            ->assertSee('Your name')
            ->assertDontSee('Full legal name');

        app(PublishQuiz::class)->handle($quiz, User::first());

        $this->get(route('quiz.play', $quiz->slug))
            ->assertSee('Full legal name');
    }

    public function test_required_questions_block_advancing(): void
    {
        [$quiz, $name, $email] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->call('next')
            ->assertHasErrors(["answers.{$name->id}", "answers.{$email->id}"])
            ->assertSet('step', 0);

        $this->assertSame(0, QuizResponse::count());
    }

    public function test_email_format_is_validated(): void
    {
        [$quiz, $name, $email] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$name->id}", 'Ada Lovelace')
            ->set("answers.{$email->id}", 'not-an-email')
            ->call('next')
            ->assertHasErrors(["answers.{$email->id}"])
            ->assertSet('step', 0);
    }

    public function test_answers_are_captured_per_page_before_completion(): void
    {
        [$quiz, $name, $email] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$name->id}", 'Ada Lovelace')
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 1);

        $response = QuizResponse::first();

        $this->assertNotNull($response);
        $this->assertSame(QuizResponse::STATUS_IN_PROGRESS, $response->status);
        $this->assertSame(1, $response->current_page);
        $this->assertSame('Ada Lovelace', $response->answers()->where('question_id', $name->id)->first()->value);
        $this->assertSame('ada@example.com', $response->answers()->where('question_id', $email->id)->first()->value);
    }

    public function test_completing_the_quiz_marks_the_response_completed(): void
    {
        [$quiz, $name, $email, $choice] = $this->publishedQuiz();
        $optionId = $choice->options()->first()->id;

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$name->id}", 'Ada Lovelace')
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next')
            ->set("answers.{$choice->id}", $optionId)
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('completed', true);

        $response = QuizResponse::first();

        $this->assertSame(QuizResponse::STATUS_COMPLETED, $response->status);
        $this->assertNotNull($response->completed_at);
        $this->assertSame(3, $response->answers()->count());
        $this->assertEquals($optionId, $response->answers()->where('question_id', $choice->id)->first()->value);
    }

    public function test_forged_option_ids_are_rejected(): void
    {
        [$quiz, $name, $email, $choice] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$name->id}", 'Ada')
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next')
            ->set("answers.{$choice->id}", 999999)
            ->call('next')
            ->assertHasErrors(["answers.{$choice->id}"])
            ->assertSet('completed', false);
    }

    public function test_respondents_resume_where_they_left_off(): void
    {
        [$quiz, $name, $email] = $this->publishedQuiz();

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$name->id}", 'Ada Lovelace')
            ->set("answers.{$email->id}", 'ada@example.com')
            ->call('next');

        // Same session, fresh visit: back on page 2 with answers intact.
        Volt::test('play', ['slug' => $quiz->slug])
            ->assertSet('step', 1)
            ->assertSet("answers.{$name->id}", 'Ada Lovelace');

        $this->assertSame(1, QuizResponse::count());
    }

    public function test_multiple_choice_answers_are_stored_as_arrays(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $page = QuizPage::factory()->for($quiz)->create();
        $question = $page->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::MultipleChoice,
            'title' => 'Pick some', 'position' => 0,
        ]);
        [$a, $b] = collect(['One', 'Two'])->map(
            fn ($label, $index) => $question->options()->create(['label' => $label, 'position' => $index])
        )->all();

        app(PublishQuiz::class)->handle($quiz, $owner);

        Volt::test('play', ['slug' => $quiz->slug])
            ->set("answers.{$question->id}", [$a->id, $b->id])
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('completed', true);

        $stored = QuizResponse::first()->answers()->first()->value;

        $this->assertEquals([$a->id, $b->id], $stored);
    }
}
