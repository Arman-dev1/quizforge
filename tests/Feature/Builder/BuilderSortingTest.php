<?php

namespace Tests\Feature\Builder;

use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BuilderSortingTest extends TestCase
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

    public function test_questions_can_be_drag_sorted_within_a_page(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz]);
        $pageId = $quiz->pages()->first()->id;

        foreach (['short_text', 'email', 'rating'] as $type) {
            $component->call('startPicking', $pageId)->call('addQuestion', $type);
        }

        [$first, $second, $third] = $quiz->pages()->first()->questions()->get()->all();

        $component->call('sortQuestion', $third->id, (string) $pageId, 0);

        $this->assertSame(0, $third->refresh()->position);
        $this->assertSame(1, $first->refresh()->position);
        $this->assertSame(2, $second->refresh()->position);
    }

    public function test_questions_can_be_drag_sorted_across_pages(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('addPage');

        [$firstPage, $secondPage] = $quiz->pages()->get()->all();

        $component->call('startPicking', $firstPage->id)->call('addQuestion', 'short_text');
        $component->call('startPicking', $firstPage->id)->call('addQuestion', 'email');
        $component->call('startPicking', $secondPage->id)->call('addQuestion', 'rating');

        [$textQuestion, $emailQuestion] = $firstPage->questions()->get()->all();
        $ratingQuestion = $secondPage->questions()->first();

        $component->call('sortQuestion', $textQuestion->id, (string) $secondPage->id, 0);

        $textQuestion->refresh();

        $this->assertSame($secondPage->id, $textQuestion->quiz_page_id);
        $this->assertSame(0, $textQuestion->position);
        $this->assertSame(1, $ratingQuestion->refresh()->position);
        $this->assertSame(0, $emailQuestion->refresh()->position);
    }

    public function test_pages_can_be_drag_sorted(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('addPage')
            ->call('addPage');

        [$first, $second, $third] = $quiz->pages()->get()->all();

        $component->call('sortPage', $third->id, null, 0);

        $this->assertSame(0, $third->refresh()->position);
        $this->assertSame(1, $first->refresh()->position);
        $this->assertSame(2, $second->refresh()->position);
    }

    public function test_options_can_be_drag_sorted(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'single_choice');

        $question = $quiz->questions()->first();
        [$optionA, $optionB, $optionC] = $question->options()->get()->all();

        $component->call('sortOption', $optionC->id, null, 0);

        $this->assertSame(0, $optionC->refresh()->position);
        $this->assertSame(1, $optionA->refresh()->position);
        $this->assertSame(2, $optionB->refresh()->position);
    }
}
