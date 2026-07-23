<?php

namespace Tests\Feature\Design;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\UsageLimits;
use App\Services\Design\QuizDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuizDesignTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Quiz} */
    protected function editorWithQuiz(WorkspaceRole $role = WorkspaceRole::Editor): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        return [$user, $quiz];
    }

    public function test_the_design_page_renders_for_editors(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();

        $this->actingAs($user)
            ->get(route('quizzes.design', $quiz))
            ->assertOk()
            ->assertSee(__('Design'))
            ->assertSee(__('Live preview'));
    }

    public function test_the_design_page_is_forbidden_to_viewers(): void
    {
        [$user, $quiz] = $this->editorWithQuiz(WorkspaceRole::Viewer);

        $this->actingAs($user)
            ->get(route('quizzes.design', $quiz))
            ->assertForbidden();
    }

    public function test_the_design_page_is_not_found_for_foreign_quizzes(): void
    {
        [$user] = $this->editorWithQuiz();
        $foreign = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.design', $foreign))
            ->assertNotFound();
    }

    public function test_saving_persists_the_design(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $design = array_merge(QuizDesign::defaults(), [
            'primary' => '#ff5722',
            'theme' => 'dark',
            'button_style' => 'pill',
        ]);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->call('save', $design)
            ->assertHasNoErrors();

        $stored = $quiz->refresh()->settings['design'];

        $this->assertSame('#ff5722', $stored['primary']);
        $this->assertSame('dark', $stored['theme']);
        $this->assertSame('pill', $stored['button_style']);
    }

    public function test_reset_restores_the_defaults(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $quiz->update(['settings' => ['design' => array_merge(QuizDesign::defaults(), ['primary' => '#000000'])]]);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->call('resetDesign');

        $this->assertSame(QuizDesign::defaults()['primary'], $quiz->refresh()->settings['design']['primary']);
    }

    public function test_free_plan_cannot_write_custom_code(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $design = array_merge(QuizDesign::defaults(), [
            'custom_css' => 'body { background: red; }',
            'custom_js' => 'alert(1)',
        ]);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->call('save', $design);

        $stored = $quiz->refresh()->settings['design'];

        $this->assertSame('', $stored['custom_css']);
        $this->assertSame('', $stored['custom_js']);
    }

    public function test_pro_plan_can_write_custom_code(): void
    {
        [$user, $quiz] = $this->editorWithQuiz();
        $this->actingAs($user);

        $this->mock(UsageLimits::class, fn ($mock) => $mock->shouldReceive('planKey')->andReturn('pro'));

        $design = array_merge(QuizDesign::defaults(), ['custom_css' => '.qf-card { border: 0; }']);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->call('save', $design);

        $this->assertSame('.qf-card { border: 0; }', $quiz->refresh()->settings['design']['custom_css']);
    }

    public function test_the_published_player_applies_the_saved_design(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create([
            'settings' => ['design' => array_merge(QuizDesign::defaults(), ['primary' => '#ff5722'])],
        ]);
        $page = QuizPage::factory()->for($quiz)->create();
        $page->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::ShortText,
            'title' => 'Your name', 'position' => 0,
        ]);

        app(PublishQuiz::class)->handle($quiz, $owner);

        $this->get(route('quiz.play', $quiz->refresh()->slug))
            ->assertOk()
            ->assertSee('qf-player')
            ->assertSee('--qf-primary:#ff5722', false);
    }

    public function test_the_design_defaults_compile_to_css_variables(): void
    {
        $vars = QuizDesign::cssVariables(QuizDesign::defaults());

        $this->assertSame('#0d9488', $vars['--qf-primary']);
        $this->assertArrayHasKey('--qf-radius', $vars);
        $this->assertArrayHasKey('--qf-font', $vars);
    }
}
