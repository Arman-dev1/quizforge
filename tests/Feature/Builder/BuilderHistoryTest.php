<?php

namespace Tests\Feature\Builder;

use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BuilderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function editorWithQuiz(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Editor)->create();
        $user->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        return [$user, $quiz];
    }

    public function test_undo_restores_a_deleted_question_with_its_options(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'single_choice')
            ->set('qTitle', 'Favourite colour?');

        $question = $quiz->questions()->first();

        $component->call('deleteQuestion', $question->id);
        $this->assertSame(0, $quiz->questions()->count());

        $component->call('undo');

        $restored = $quiz->questions()->first();

        $this->assertNotNull($restored);
        $this->assertSame('Favourite colour?', $restored->title);
        $this->assertSame(3, $restored->options()->count());
    }

    public function test_redo_reapplies_an_undone_deletion(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'short_text');

        $component->call('deleteQuestion', $quiz->questions()->first()->id);
        $component->call('undo');
        $this->assertSame(1, $quiz->questions()->count());

        $component->call('redo');
        $this->assertSame(0, $quiz->questions()->count());
    }

    public function test_undo_restores_a_deleted_page_with_its_questions(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('addPage');

        $secondPage = $quiz->pages()->get()->last();

        $component->call('startPicking', $secondPage->id)
            ->call('addQuestion', 'email')
            ->call('deletePage', $secondPage->id);

        $this->assertSame(1, $quiz->pages()->count());

        $component->call('undo');

        $this->assertSame(2, $quiz->pages()->count());
        $this->assertSame(1, $quiz->questions()->count());
    }

    public function test_undo_with_no_history_is_a_no_op(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('undo')
            ->assertHasNoErrors();

        $this->assertSame(1, $quiz->pages()->count());
    }

    public function test_a_new_action_clears_the_redo_stack(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'short_text');

        $component->call('deleteQuestion', $quiz->questions()->first()->id);
        $component->call('undo');

        // A fresh mutation should invalidate redo.
        $component->call('addPage');
        $component->call('redo');

        // Redo did nothing: the question survives and both pages remain.
        $this->assertSame(1, $quiz->questions()->count());
        $this->assertSame(2, $quiz->pages()->count());
    }

    public function test_text_edits_do_not_pollute_the_undo_stack(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'short_text');

        // One history entry from addQuestion; title edits add none.
        $component->set('qTitle', 'First title')
            ->set('qTitle', 'Second title');

        $component->call('undo');

        // Undo reverts the addQuestion, not a title change.
        $this->assertSame(0, $quiz->questions()->count());
    }
}
