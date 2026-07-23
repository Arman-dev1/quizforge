<?php

namespace Tests\Feature\Builder;

use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuestionBuilderTest extends TestCase
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

    public function test_builder_is_displayed_to_editors_and_creates_the_first_page(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user)
            ->get(route('quizzes.builder', $quiz))
            ->assertOk();

        $this->assertSame(1, $quiz->pages()->count());
    }

    public function test_pill_toggles_flip_required_and_hidden_and_persist(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'short_text');

        $question = $quiz->questions()->first();

        $component->call('toggleFlag', 'qRequired')->assertSet('qRequired', true);
        $this->assertTrue($question->refresh()->is_required);

        $component->call('toggleFlag', 'qHidden')->assertSet('qHidden', true);
        $this->assertTrue($question->refresh()->is_hidden);
    }

    public function test_publish_from_the_builder_snapshots_a_version(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'short_text')
            ->set('qTitle', 'Your name')
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertSame(QuizStatus::Published, $quiz->refresh()->status);
        $this->assertSame(1, $quiz->versions()->count());
    }

    public function test_publish_surfaces_a_validation_error_when_there_is_nothing_to_publish(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('publish')
            ->assertHasErrors('publish');

        $this->assertSame(0, $quiz->versions()->count());
    }

    public function test_changing_type_reconciles_options(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'single_choice');

        $question = $quiz->questions()->first();
        $this->assertSame(3, $question->options()->count());

        // Switch to a type without options: options are dropped.
        $component->call('changeType', 'short_text');
        $this->assertSame(QuestionType::ShortText, $question->refresh()->type);
        $this->assertSame(0, $question->options()->count());

        // Switch back to a choice type: defaults are re-seeded.
        $component->call('changeType', 'multiple_choice');
        $this->assertSame(QuestionType::MultipleChoice, $question->refresh()->type);
        $this->assertGreaterThan(0, $question->options()->count());
    }

    public function test_changing_to_a_single_answer_type_keeps_one_correct_option(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'multiple_choice');

        $question = $quiz->questions()->first();
        [$a, $b] = $question->options()->orderBy('position')->take(2)->get()->all();

        $component->call('toggleCorrect', $a->id)->call('toggleCorrect', $b->id);
        $this->assertSame(2, $question->options()->where('is_correct', true)->count());

        $component->call('changeType', 'single_choice');
        $this->assertSame(1, $question->refresh()->options()->where('is_correct', true)->count());
    }

    public function test_builder_is_forbidden_to_viewers(): void
    {
        [$user, $quiz] = $this->editorWithQuiz(WorkspaceRole::Viewer);

        $this->actingAs($user)
            ->get(route('quizzes.builder', $quiz))
            ->assertForbidden();
    }

    public function test_builder_is_not_found_for_foreign_quizzes(): void
    {
        [$user] = $this->editorWithQuiz();
        $foreign = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.builder', $foreign))
            ->assertNotFound();
    }

    public function test_pages_can_be_added_and_reordered(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('addPage');

        $this->assertSame(2, $quiz->pages()->count());

        [$first, $second] = $quiz->pages()->get()->all();

        $component->call('movePage', $second->id, -1);

        $this->assertSame(0, $second->refresh()->position);
        $this->assertSame(1, $first->refresh()->position);
    }

    public function test_the_last_page_cannot_be_deleted(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz]);
        $page = $quiz->pages()->first();

        $component->call('deletePage', $page->id)
            ->assertHasErrors(['builder']);

        $this->assertSame(1, $quiz->pages()->count());
    }

    public function test_deleting_a_page_removes_its_questions(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('addPage');

        $secondPage = $quiz->pages()->get()->last();

        $component->call('startPicking', $secondPage->id)
            ->call('addQuestion', 'short_text')
            ->call('deletePage', $secondPage->id);

        $this->assertSame(1, $quiz->pages()->count());
        $this->assertSame(0, $quiz->questions()->count());
    }

    public function test_questions_are_created_with_type_defaults_and_options(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $page = null;

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $page = $quiz->pages()->first()->id)
            ->call('addQuestion', 'single_choice')
            ->assertHasNoErrors();

        $question = $quiz->questions()->first();

        $this->assertSame(QuestionType::SingleChoice, $question->type);
        $this->assertSame(3, $question->options()->count());
        $this->assertSame(0, $question->position);
    }

    public function test_rating_questions_get_default_scale_settings(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'rating');

        $this->assertSame(['max' => 5], $quiz->questions()->first()->settings);
    }

    public function test_question_fields_autosave(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'short_text')
            ->set('qTitle', 'What is your name?')
            ->set('qRequired', true)
            ->set('qHelpText', 'First and last name');

        $question = $quiz->questions()->first();

        $this->assertSame('What is your name?', $question->title);
        $this->assertTrue($question->is_required);
        $this->assertSame('First and last name', $question->help_text);
    }

    public function test_scale_settings_are_clamped(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'rating')
            ->set('qSettings.max', 99);

        $this->assertSame(10, $quiz->questions()->first()->settings['max']);
    }

    public function test_matrix_rows_and_columns_are_edited_as_lines(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'matrix')
            ->set('qMatrixRows', "Speed\nQuality\n\nSupport");

        $this->assertSame(['Speed', 'Quality', 'Support'], $quiz->questions()->first()->settings['rows']);
    }

    public function test_questions_can_be_duplicated_with_options(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'multiple_choice')
            ->set('qTitle', 'Pick some');

        $original = $quiz->questions()->first();

        $component->call('duplicateQuestion', $original->id);

        $this->assertSame(2, $quiz->questions()->count());

        $copy = $quiz->questions()->where('id', '!=', $original->id)->first();

        $this->assertSame('Pick some', $copy->title);
        $this->assertSame(3, $copy->options()->count());
        $this->assertSame(1, $copy->position);
    }

    public function test_questions_can_be_reordered_and_positions_reindex_after_deletion(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz]);

        $pageId = $quiz->pages()->first()->id;

        foreach (['short_text', 'email', 'rating'] as $type) {
            $component->call('startPicking', $pageId)->call('addQuestion', $type);
        }

        [$first, $second, $third] = $quiz->pages()->first()->questions()->get()->all();

        $component->call('moveQuestion', $third->id, -1);

        $this->assertSame(1, $third->refresh()->position);
        $this->assertSame(2, $second->refresh()->position);

        $component->call('deleteQuestion', $third->id);

        $this->assertSame(0, $first->refresh()->position);
        $this->assertSame(1, $second->refresh()->position);
    }

    public function test_options_can_be_added_renamed_and_removed_with_a_minimum_of_one(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'dropdown')
            ->call('addOption');

        $question = $quiz->questions()->first();
        $this->assertSame(4, $question->options()->count());

        $option = $question->options()->first();

        $component->set('optionLabels.'.$option->id, 'Renamed option');
        $this->assertSame('Renamed option', $option->refresh()->label);

        foreach ($question->options()->get()->slice(1) as $extra) {
            $component->call('removeOption', $extra->id);
        }

        $this->assertSame(1, $question->options()->count());

        $component->call('removeOption', $option->id)
            ->assertHasErrors(['options']);

        $this->assertSame(1, $question->options()->count());
    }

    public function test_single_choice_correct_answers_are_exclusive(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'single_choice');

        $question = $quiz->questions()->first();
        [$optionA, $optionB] = $question->options()->get()->all();

        $component->call('toggleCorrect', $optionA->id);
        $this->assertTrue($optionA->refresh()->is_correct);

        $component->call('toggleCorrect', $optionB->id);

        $this->assertTrue($optionB->refresh()->is_correct);
        $this->assertFalse($optionA->refresh()->is_correct);
    }

    public function test_multiple_choice_allows_several_correct_answers(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $quiz->pages()->first()->id)
            ->call('addQuestion', 'multiple_choice');

        $question = $quiz->questions()->first();
        [$optionA, $optionB] = $question->options()->get()->all();

        $component->call('toggleCorrect', $optionA->id)
            ->call('toggleCorrect', $optionB->id);

        $this->assertTrue($optionA->refresh()->is_correct);
        $this->assertTrue($optionB->refresh()->is_correct);
    }

    public function test_page_titles_autosave(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user);

        $component = Volt::test('quizzes.builder', ['quiz' => $quiz]);
        $page = $quiz->pages()->first();

        $component->set('pageTitles.'.$page->id, 'Welcome section');

        $this->assertSame('Welcome section', $page->refresh()->title);
    }
}
