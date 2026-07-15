<?php

namespace Tests\Feature\Quizzes;

use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PublishQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function editorWithQuiz(WorkspaceRole $role = WorkspaceRole::Editor): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        return [$user, $quiz];
    }

    protected function addQuestion(Quiz $quiz, QuestionType $type = QuestionType::ShortText, string $title = 'A question?'): void
    {
        $page = $quiz->pages()->first() ?? QuizPage::factory()->for($quiz)->create();

        $question = $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => $type,
            'title' => $title,
            'position' => $page->questions()->count(),
            'settings' => $type->defaultSettings(),
        ]);

        foreach ($type->defaultOptionLabels() as $index => $label) {
            $question->options()->create(['label' => $label, 'position' => $index]);
        }
    }

    public function test_publishing_creates_an_immutable_version_snapshot(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->addQuestion($quiz, QuestionType::SingleChoice, 'Pick one');

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->assertHasNoErrors();

        $quiz->refresh();
        $version = $quiz->latestVersion();

        $this->assertSame(QuizStatus::Published, $quiz->status);
        $this->assertNotNull($quiz->published_at);
        $this->assertSame(1, $version->version);
        $this->assertSame('Pick one', $version->pages()[0]['questions'][0]['title']);
        $this->assertCount(3, $version->pages()[0]['questions'][0]['options']);
    }

    public function test_publishing_requires_at_least_one_visible_question(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        QuizPage::factory()->for($quiz)->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->assertHasErrors(['publish']);

        $this->assertSame(QuizStatus::Draft, $quiz->refresh()->status);
    }

    public function test_publishing_requires_question_titles(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->addQuestion($quiz, QuestionType::ShortText, '');

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->assertHasErrors(['publish']);
    }

    public function test_publishing_requires_options_on_choice_questions(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->addQuestion($quiz, QuestionType::SingleChoice, 'Pick one');
        $quiz->questions()->first()->options()->delete();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->assertHasErrors(['publish']);
    }

    public function test_republishing_increments_the_version(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->addQuestion($quiz);

        $this->actingAs($user);

        $component = Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->call('publish');

        $this->assertSame(2, $quiz->versions()->count());
        $this->assertSame(2, $quiz->latestVersion()->version);
    }

    public function test_published_quizzes_can_be_closed_and_reopened(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->addQuestion($quiz);

        $this->actingAs($user);

        $component = Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->call('closeQuiz');

        $this->assertSame(QuizStatus::Closed, $quiz->refresh()->status);

        $component->call('reopen');

        $this->assertSame(QuizStatus::Published, $quiz->refresh()->status);
        $this->assertSame(1, $quiz->versions()->count());
    }

    public function test_viewers_cannot_publish(): void
    {
        [$user, $quiz] = $this->editorWithQuiz(WorkspaceRole::Viewer);
        $this->addQuestion($quiz);

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('publish')
            ->assertForbidden();

        $this->assertSame(QuizStatus::Draft, $quiz->refresh()->status);
    }
}
