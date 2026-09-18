<?php

namespace Tests\Feature\Quizzes;

use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Scoring\ResultResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The Results tab has to survive being *rendered* in every mode.
 *
 * Nothing here tested the rendered output before, only ResultResolver in
 * isolation — which is how a bad Flux icon name in the rich-text toolbar
 * reached production. Switching to score or category draws an outcome
 * editor, and with it a rich-text description field, so those two modes
 * exercise markup that simple mode never touches.
 */
class ResultsTabTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Quiz} */
    protected function quizWithChoiceQuestion(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $page = QuizPage::factory()->for($quiz)->create(['position' => 0]);
        $question = $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => QuestionType::SingleChoice,
            'title' => 'Pick one',
            'position' => 0,
        ]);

        $question->options()->create(['label' => 'A', 'position' => 0, 'settings' => ['category' => 'Explorer']]);
        $question->options()->create(['label' => 'B', 'position' => 1, 'settings' => ['category' => 'Builder']]);

        return [$owner, $quiz->refresh()];
    }

    public function test_the_results_tab_renders_in_simple_mode(): void
    {
        [$owner, $quiz] = $this->quizWithChoiceQuestion();

        $this->actingAs($owner)
            ->get(route('quizzes.results', $quiz))
            ->assertOk();
    }

    public function test_switching_to_score_mode_renders(): void
    {
        [$owner, $quiz] = $this->quizWithChoiceQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.results', ['quiz' => $quiz])
            ->call('setMode', ResultResolver::MODE_SCORE)
            ->assertHasNoErrors()
            ->assertSet('mode', ResultResolver::MODE_SCORE)
            ->assertOk();
    }

    public function test_switching_to_category_mode_renders(): void
    {
        [$owner, $quiz] = $this->quizWithChoiceQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.results', ['quiz' => $quiz])
            ->call('setMode', ResultResolver::MODE_CATEGORY)
            ->assertHasNoErrors()
            ->assertSet('mode', ResultResolver::MODE_CATEGORY)
            ->assertOk();
    }

    /**
     * Score mode seeds a first outcome on its own; this pins the rich-text
     * description field being drawn, which is where the icon blew up.
     */
    public function test_score_mode_draws_an_outcome_editor(): void
    {
        [$owner, $quiz] = $this->quizWithChoiceQuestion();

        $this->actingAs($owner);

        $component = Volt::test('quizzes.results', ['quiz' => $quiz])
            ->call('setMode', ResultResolver::MODE_SCORE);

        $this->assertNotEmpty($component->get('outcomes'), 'Score mode should seed an outcome to edit.');

        $component->assertSee('qf-rich-text', escape: false);
    }

    public function test_adding_an_outcome_renders(): void
    {
        [$owner, $quiz] = $this->quizWithChoiceQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.results', ['quiz' => $quiz])
            ->call('setMode', ResultResolver::MODE_CATEGORY)
            ->call('addOutcome')
            ->assertHasNoErrors()
            ->assertOk();
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        [$owner, $quiz] = $this->quizWithChoiceQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.results', ['quiz' => $quiz])
            ->call('setMode', 'something-else')
            ->assertSet('mode', ResultResolver::MODE_SIMPLE);
    }
}
