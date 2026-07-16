<?php

namespace Tests\Feature\Templates;

use App\Actions\Quizzes\CreateQuizFromTemplate;
use App\Actions\Quizzes\SaveQuizAsTemplate;
use App\Enums\QuestionType;
use App\Enums\QuizType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizTemplate;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\TemplateSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function editor(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Editor)->create();
        $user->switchToWorkspace($workspace);

        return $user;
    }

    public function test_the_seeder_creates_global_templates_that_instantiate_fully(): void
    {
        $this->seed(TemplateSeeder::class);

        $this->assertGreaterThanOrEqual(6, QuizTemplate::whereNull('workspace_id')->count());

        $user = $this->editor();
        $template = QuizTemplate::where('name', 'General Knowledge Quiz')->first();

        $quiz = app(CreateQuizFromTemplate::class)->handle($user, $template);

        $this->assertSame($user->current_workspace_id, $quiz->workspace_id);
        $this->assertSame(QuizType::GeneralQuiz, $quiz->type);
        $this->assertTrue($quiz->settings['scored']);
        $this->assertSame(5, $quiz->questions()->count());
        $this->assertSame(4, $quiz->questions()->first()->options()->count());
        $this->assertSame(1, $quiz->questions()->first()->options()->where('is_correct', true)->count());
    }

    public function test_seeding_twice_does_not_duplicate_templates(): void
    {
        $this->seed(TemplateSeeder::class);
        $this->seed(TemplateSeeder::class);

        $this->assertSame(
            QuizTemplate::whereNull('workspace_id')->count(),
            QuizTemplate::whereNull('workspace_id')->distinct()->count('name'),
        );
    }

    public function test_using_a_template_from_the_create_page_lands_in_the_builder(): void
    {
        $this->seed(TemplateSeeder::class);
        $user = $this->editor();

        $this->actingAs($user);

        $template = QuizTemplate::where('name', 'Lead Generation Quiz')->first();

        Volt::test('quizzes.create')
            ->call('useTemplate', $template->id)
            ->assertHasNoErrors();

        $quiz = Quiz::withoutGlobalScope('workspace')->where('name', 'Lead Generation Quiz')->first();

        $this->assertNotNull($quiz);
        $this->assertSame(5, $quiz->questions()->count());
    }

    public function test_save_as_template_and_reinstantiation_remaps_logic_ids(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        $quiz = Quiz::factory()->inWorkspace($user->currentWorkspace)->create();

        $pageOne = QuizPage::factory()->for($quiz)->create(['position' => 0]);
        $trigger = $pageOne->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::SingleChoice,
            'title' => 'Own a car?', 'position' => 0,
        ]);
        $yes = $trigger->options()->create(['label' => 'Yes', 'position' => 0]);

        $pageTwo = QuizPage::factory()->for($quiz)->create(['position' => 1]);
        $pageTwo->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Which brand?', 'position' => 0,
            'logic' => ['match' => 'all', 'conditions' => [
                ['question_id' => $trigger->id, 'operator' => 'equals', 'value' => (string) $yes->id],
            ]],
        ]);

        $template = app(SaveQuizAsTemplate::class)->handle($quiz, $user);

        $this->assertSame($quiz->workspace_id, $template->workspace_id);

        $copy = app(CreateQuizFromTemplate::class)->handle($user, $template, 'From template');

        $newTrigger = $copy->questions()->where('title', 'Own a car?')->first();
        $newFollowUp = $copy->questions()->where('title', 'Which brand?')->first();
        $newYes = $newTrigger->options()->first();

        $condition = $newFollowUp->logic['conditions'][0];

        $this->assertSame($newTrigger->id, $condition['question_id']);
        $this->assertSame((string) $newYes->id, $condition['value']);
        $this->assertNotSame($trigger->id, $newTrigger->id);
    }

    public function test_workspace_templates_are_invisible_to_other_workspaces(): void
    {
        $owner = $this->editor();
        QuizTemplate::factory()->create([
            'workspace_id' => $owner->current_workspace_id,
            'name' => 'Private Playbook',
        ]);

        $stranger = $this->editor();

        $this->assertFalse(
            QuizTemplate::availableTo($stranger->currentWorkspace)->where('name', 'Private Playbook')->exists(),
        );

        $this->assertTrue(
            QuizTemplate::availableTo($owner->currentWorkspace)->where('name', 'Private Playbook')->exists(),
        );
    }

    public function test_global_templates_cannot_be_deleted_from_the_create_page(): void
    {
        $this->seed(TemplateSeeder::class);
        $user = $this->editor();

        $this->actingAs($user);

        $template = QuizTemplate::whereNull('workspace_id')->first();

        try {
            Volt::test('quizzes.create')->call('deleteTemplate', $template->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            // Global templates are not deletable, as intended.
        }

        $this->assertNotNull($template->fresh());
    }
}
