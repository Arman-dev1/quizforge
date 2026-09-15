<?php

namespace Tests\Feature\Design;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Design\QuizDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A published quiz renders in the design its author chose — never in the
 * respondent's OS theme.
 *
 * The player used to ship Flux's appearance script, which writes `.dark` onto
 * <html> from the visitor's system preference. Every `dark:` utility in the
 * player then flipped while the author's --qf-* variables did not, so a
 * respondent on a dark phone got a dark card with dark text sitting on the
 * author's light background.
 */
class PlayerThemeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function publishedQuiz(array $design = []): Quiz
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

        return $quiz->refresh();
    }

    public function test_the_player_does_not_ship_the_appearance_script(): void
    {
        $quiz = $this->publishedQuiz();

        $html = $this->get(route('quiz.play', $quiz->slug))->assertOk()->getContent();

        // Flux's appearance script is what sets `.dark` from the OS setting.
        $this->assertStringNotContainsString('flux.appearance', $html);
        $this->assertStringNotContainsString('localStorage.getItem(\'flux.appearance\')', $html);
    }

    public function test_the_player_card_carries_no_os_theme_classes(): void
    {
        $quiz = $this->publishedQuiz();

        $html = $this->get(route('quiz.play', $quiz->slug))->assertOk()->getContent();

        // Grab the player subtree only — the layout around it is not themed.
        $player = substr($html, (int) strpos($html, 'id="qf-player"'));

        // Our own surfaces are variable-driven now.
        $this->assertStringNotContainsString('dark:bg-zinc-900', $player);
        $this->assertStringNotContainsString('dark:border-zinc-800', $player);
        $this->assertStringNotContainsString('dark:bg-zinc-800', $player);

        /*
         | Flux's own components still emit `dark:text-white` and friends, and
         | that is fine: `.dark` is never set on this page (see the test
         | above), and app.css overrides colour for the player subtree at ID
         | specificity anyway. What must not come back is the appearance
         | script that would make those variants live.
         */
        $this->assertStringContainsString('id="qf-player"', $player);
    }

    public function test_the_author_theme_reaches_the_player(): void
    {
        $quiz = $this->publishedQuiz(QuizDesign::applyTheme([], 'dark'));

        $html = $this->get(route('quiz.play', $quiz->slug))->assertOk()->getContent();

        $dark = QuizDesign::themes()['dark'];

        $this->assertStringContainsString('--qf-card:'.$dark['card'], str_replace(' ', '', $html));
        $this->assertStringContainsString('--qf-text:'.$dark['text'], str_replace(' ', '', $html));
    }

    public function test_choosing_a_theme_moves_the_background_with_it(): void
    {
        foreach (QuizDesign::themes() as $key => $tokens) {
            $design = QuizDesign::applyTheme(QuizDesign::defaults(), $key);

            $this->assertSame($tokens['page'], $design['background_color'], "{$key}: background did not follow the theme");
            $this->assertSame($tokens['gradient_from'], $design['gradient_from']);
            $this->assertSame($tokens['gradient_to'], $design['gradient_to']);
        }
    }

    public function test_applying_a_theme_leaves_the_authors_own_choices_alone(): void
    {
        $design = QuizDesign::applyTheme([
            'primary' => '#ff0000',
            'font_family' => 'serif',
            'border_radius' => 24,
            'hide_branding' => true,
        ], 'dark');

        $this->assertSame('#ff0000', $design['primary']);
        $this->assertSame('serif', $design['font_family']);
        $this->assertSame(24, $design['border_radius']);
        $this->assertTrue($design['hide_branding']);
        $this->assertSame('dark', $design['theme']);
    }

    public function test_an_unknown_theme_changes_nothing(): void
    {
        $design = QuizDesign::applyTheme(QuizDesign::defaults(), 'neon-disco');

        $this->assertSame(QuizDesign::defaults()['theme'], $design['theme']);
        $this->assertSame(QuizDesign::defaults()['background_color'], $design['background_color']);
    }

    /**
     * Every theme's body text must be legible on the background that theme
     * ships with — the quiz title renders straight onto it, outside the card.
     */
    public function test_every_theme_is_legible_on_its_own_background(): void
    {
        foreach (QuizDesign::themes() as $key => $t) {
            foreach (['text' => 4.5, 'muted' => 3.0] as $token => $minimum) {
                $ratio = $this->contrast($t[$token], $t['page']);

                $this->assertGreaterThanOrEqual(
                    $minimum,
                    $ratio,
                    sprintf('%s theme: %s (%s) on page %s is only %.2f:1', $key, $token, $t[$token], $t['page'], $ratio),
                );
            }

            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($t['text'], $t['card']),
                "{$key} theme: card text is not legible on the card",
            );
        }
    }

    /** WCAG relative-contrast ratio between two hex colours. */
    protected function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            [$r, $g, $bl] = array_map(
                fn (string $c) => ($v = hexdec($c) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
                str_split(ltrim($hex, '#'), 2),
            );

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bl;
        };

        $one = $lum($a);
        $two = $lum($b);

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }
}
