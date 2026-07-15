<?php

namespace Tests\Feature\Quizzes;

use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuizPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function memberWithQuiz(WorkspaceRole $role = WorkspaceRole::Viewer): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        return [$user, $quiz];
    }

    public function test_viewers_can_open_the_preview(): void
    {
        [$user, $quiz] = $this->memberWithQuiz();
        $page = QuizPage::factory()->for($quiz)->create();
        $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => QuestionType::ShortText,
            'title' => 'What is your name?',
            'position' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('quizzes.preview', $quiz))
            ->assertOk()
            ->assertSee('What is your name?')
            ->assertSee(__('Preview mode — responses are not saved.'));
    }

    public function test_hidden_questions_are_not_rendered(): void
    {
        [$user, $quiz] = $this->memberWithQuiz();
        $page = QuizPage::factory()->for($quiz)->create();

        $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => QuestionType::ShortText,
            'title' => 'Visible question',
            'position' => 0,
        ]);

        $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => QuestionType::ShortText,
            'title' => 'Hidden question',
            'is_hidden' => true,
            'position' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('quizzes.preview', $quiz))
            ->assertOk()
            ->assertSee('Visible question')
            ->assertDontSee('Hidden question');
    }

    public function test_every_question_type_renders(): void
    {
        [$user, $quiz] = $this->memberWithQuiz();
        $page = QuizPage::factory()->for($quiz)->create();

        foreach (QuestionType::cases() as $index => $type) {
            $question = $page->questions()->create([
                'quiz_id' => $quiz->id,
                'type' => $type,
                'title' => 'Question '.$type->value,
                'position' => $index,
                'settings' => $type->defaultSettings(),
            ]);

            foreach ($type->defaultOptionLabels() as $optionIndex => $label) {
                $question->options()->create(['label' => $label, 'position' => $optionIndex]);
            }
        }

        $response = $this->actingAs($user)->get(route('quizzes.preview', $quiz));

        $response->assertOk();

        foreach (QuestionType::cases() as $type) {
            $response->assertSee('Question '.$type->value);
        }
    }

    public function test_navigation_moves_between_pages_and_clamps(): void
    {
        [$user, $quiz] = $this->memberWithQuiz();
        QuizPage::factory()->for($quiz)->create(['title' => 'Intro', 'position' => 0]);
        QuizPage::factory()->for($quiz)->create(['title' => 'Details', 'position' => 1]);

        $this->actingAs($user);

        $component = Volt::test('quizzes.preview', ['quiz' => $quiz]);

        $component->assertSee('Intro')
            ->call('next')
            ->assertSee('Details')
            ->call('next')
            ->assertSee('Details')
            ->call('previous')
            ->assertSee('Intro')
            ->call('previous')
            ->assertSee('Intro');
    }

    public function test_empty_quizzes_show_an_empty_state(): void
    {
        [$user, $quiz] = $this->memberWithQuiz();

        $this->actingAs($user)
            ->get(route('quizzes.preview', $quiz))
            ->assertOk()
            ->assertSee(__('Nothing to preview yet'));
    }

    public function test_foreign_quizzes_cannot_be_previewed(): void
    {
        [$user] = $this->memberWithQuiz();
        $foreign = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.preview', $foreign))
            ->assertNotFound();
    }
}
