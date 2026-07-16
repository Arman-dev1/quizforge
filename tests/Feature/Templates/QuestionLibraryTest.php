<?php

namespace Tests\Feature\Templates;

use App\Enums\WorkspaceRole;
use App\Models\LibraryQuestion;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuestionLibraryTest extends TestCase
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

    public function test_questions_can_be_saved_to_the_library_from_the_builder(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'single_choice')
            ->set('qTitle', 'Reusable choice')
            ->call('saveToLibrary');

        $item = LibraryQuestion::withoutGlobalScope('workspace')->first();

        $this->assertNotNull($item);
        $this->assertSame($user->current_workspace_id, $item->workspace_id);
        $this->assertSame('Reusable choice', $item->name);
        $this->assertCount(3, $item->question['options']);
    }

    public function test_library_questions_can_be_inserted_into_a_page(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $item = LibraryQuestion::factory()->create(['workspace_id' => $user->current_workspace_id]);

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addLibraryQuestion', $item->id)
            ->assertHasNoErrors();

        $question = $quiz->questions()->first();

        $this->assertNotNull($question);
        $this->assertSame('Saved question', $question->title);
        $this->assertSame(2, $question->options()->count());
    }

    public function test_library_items_of_other_workspaces_are_not_usable(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $foreign = LibraryQuestion::factory()->create();

        $this->actingAs($user);

        try {
            Volt::test('quizzes.builder', ['quiz' => $quiz])
                ->call('startPicking', $quiz->pages()->first()->id)
                ->call('addLibraryQuestion', $foreign->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            // Foreign library items are invisible, as intended.
        }

        $this->assertSame(0, $quiz->questions()->count());
    }

    public function test_library_items_can_be_deleted(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $item = LibraryQuestion::factory()->create(['workspace_id' => $user->current_workspace_id]);

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('deleteLibraryQuestion', $item->id);

        $this->assertNull($item->fresh());
    }
}
