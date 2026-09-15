<?php

namespace Tests\Feature\Design;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * "Powered by QuizForge" comes off on a paid plan only.
 *
 * The check is made where the branding is rendered, not just where the
 * toggle is drawn — a stored setting must stop taking effect the moment a
 * workspace drops back to Free.
 */
class BrandingRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected const BRANDING = 'Powered by';

    protected function quizWithQuestion(array $design = []): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create([
            'settings' => $design ? ['design' => $design] : [],
        ]);

        $page = QuizPage::factory()->for($quiz)->create(['position' => 0]);
        $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => QuestionType::ShortText,
            'title' => 'Your name',
            'position' => 0,
        ]);

        app(PublishQuiz::class)->handle($quiz, $owner);

        return [$owner, $quiz->refresh()];
    }

    /** Give the current (free) plan the paid branding flag. */
    protected function grantBrandingRemoval(): void
    {
        config(['plans.free.flags.remove_branding' => true]);
    }

    public function test_the_free_plan_always_shows_the_branding(): void
    {
        [, $quiz] = $this->quizWithQuestion();

        $this->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertSee(self::BRANDING);
    }

    public function test_a_free_plan_cannot_hide_it_even_if_the_setting_is_stored(): void
    {
        // Someone downgrades, or posts the setting directly.
        [, $quiz] = $this->quizWithQuestion(['hide_branding' => true]);

        $this->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertSee(self::BRANDING);
    }

    public function test_a_paid_plan_with_the_setting_on_hides_it(): void
    {
        $this->grantBrandingRemoval();

        [, $quiz] = $this->quizWithQuestion(['hide_branding' => true]);

        $this->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertDontSee(self::BRANDING);
    }

    public function test_a_paid_plan_still_shows_it_until_the_author_turns_it_off(): void
    {
        $this->grantBrandingRemoval();

        [, $quiz] = $this->quizWithQuestion();

        $this->get(route('quiz.play', $quiz->slug))
            ->assertOk()
            ->assertSee(self::BRANDING);
    }

    public function test_the_preview_matches_the_player(): void
    {
        $this->grantBrandingRemoval();

        [$owner, $quiz] = $this->quizWithQuestion(['hide_branding' => true]);

        $this->actingAs($owner)
            ->get(route('quizzes.preview', $quiz))
            ->assertOk()
            ->assertDontSee(self::BRANDING);
    }

    public function test_the_toggle_is_locked_on_the_free_plan(): void
    {
        [$owner, $quiz] = $this->quizWithQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->assertSet('canRemoveBranding', false)
            ->assertSee(__('Removing QuizForge branding is a Pro feature.'));
    }

    public function test_the_toggle_is_available_on_a_paid_plan(): void
    {
        $this->grantBrandingRemoval();

        [$owner, $quiz] = $this->quizWithQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->assertSet('canRemoveBranding', true)
            ->assertSee(__('Hide “Powered by QuizForge”'));
    }

    public function test_a_free_plan_cannot_save_the_setting(): void
    {
        [$owner, $quiz] = $this->quizWithQuestion();

        $this->actingAs($owner);

        // The toggle is hidden, but save() takes a client-supplied payload.
        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->call('save', ['hide_branding' => true]);

        $this->assertFalse($quiz->refresh()->settings['design']['hide_branding'] ?? false);
    }

    public function test_a_paid_plan_can_save_the_setting(): void
    {
        $this->grantBrandingRemoval();

        [$owner, $quiz] = $this->quizWithQuestion();

        $this->actingAs($owner);

        Volt::test('quizzes.design', ['quiz' => $quiz])
            ->call('save', ['hide_branding' => true]);

        $this->assertTrue($quiz->refresh()->settings['design']['hide_branding']);
    }
}
