<?php

namespace Tests\Feature\Player;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\WorkspaceRole;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizPage;
use App\Models\QuizResponse;
use App\Models\QuizView;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Every question type, answered and submitted — on the public player and on
 * the author's preview.
 *
 * These are the types customers actually build with, so "it renders" is not
 * enough: each one has to accept an answer, survive validation, and store the
 * right shape (or, on preview, store nothing at all).
 */
class EveryQuestionTypeTest extends TestCase
{
    use RefreshDatabase;

    /** Types that cannot be answered in the player yet. */
    protected const UNSUPPORTED = [QuestionType::FileUpload, QuestionType::Signature];

    protected User $owner;

    protected Quiz $quiz;

    /** @var array<string, Question> keyed by type value */
    protected array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($this->owner, WorkspaceRole::Owner)->create();
        $this->owner->switchToWorkspace($workspace);

        $this->quiz = Quiz::factory()->inWorkspace($workspace)->create(['settings' => []]);

        $page = QuizPage::factory()->for($this->quiz)->create(['position' => 0]);

        foreach (QuestionType::cases() as $index => $type) {
            $question = $page->questions()->create([
                'quiz_id' => $this->quiz->id,
                'type' => $type,
                'title' => 'Q '.$type->value,
                'position' => $index,
                'settings' => $type->defaultSettings(),
            ]);

            foreach ($type->defaultOptionLabels() as $optionIndex => $label) {
                $question->options()->create(['label' => $label, 'position' => $optionIndex]);
            }

            $this->questions[$type->value] = $question->fresh('options');
        }
    }

    protected function publish(): void
    {
        app(PublishQuiz::class)->handle($this->quiz, $this->owner);
        $this->quiz->refresh();
    }

    /** A valid answer for each type, in the shape the player produces. */
    protected function answerFor(QuestionType $type): mixed
    {
        $question = $this->questions[$type->value];
        $optionIds = $question->options->pluck('id')->all();
        $settings = $question->settings ?? [];

        return match ($type) {
            QuestionType::SingleChoice,
            QuestionType::Dropdown,
            QuestionType::ImageChoice => $optionIds[0],

            // Deliberately more than one — this is what was broken.
            QuestionType::MultipleChoice => array_slice($optionIds, 0, 2),

            QuestionType::Ranking => array_reverse($optionIds),
            QuestionType::YesNo => 'yes',
            QuestionType::ShortText => 'A short answer',
            QuestionType::LongText => "A longer answer\nacross lines",
            QuestionType::Email => 'person@example.com',
            QuestionType::Phone => '+44 7700 900123',
            QuestionType::Website => 'https://example.com',
            QuestionType::Address => [
                'street' => '1 High Street', 'city' => 'Leeds',
                'state' => 'West Yorkshire', 'postal_code' => 'LS1 1AA', 'country' => 'UK',
            ],
            QuestionType::Rating => (int) ($settings['max'] ?? 5),
            QuestionType::OpinionScale, QuestionType::LinearScale => (int) ($settings['min'] ?? 1),
            QuestionType::Nps => 9,
            QuestionType::Number => 42,
            QuestionType::Date => '2026-03-14',
            QuestionType::Time => '14:30',
            QuestionType::Matrix => collect(array_keys($settings['rows'] ?? []))
                ->mapWithKeys(fn ($rowIndex) => [$rowIndex => array_key_first($settings['columns'] ?? [0 => ''])])
                ->all(),

            QuestionType::FileUpload, QuestionType::Signature => null,
        };
    }

    /** Every answerable type, as `answers.{id}` => value. */
    protected function fullAnswerSet(): array
    {
        $answers = [];

        foreach (QuestionType::cases() as $type) {
            if (in_array($type, self::UNSUPPORTED, true)) {
                continue;
            }

            $answers['answers.'.$this->questions[$type->value]->id] = $this->answerFor($type);
        }

        return $answers;
    }

    // ── The public player ─────────────────────────────────────

    public function test_every_question_type_renders_on_the_player(): void
    {
        $this->publish();

        $response = $this->get(route('quiz.play', $this->quiz->slug))->assertOk();

        foreach (QuestionType::cases() as $type) {
            $response->assertSee('Q '.$type->value);
        }
    }

    public function test_every_question_type_can_be_answered_and_submitted(): void
    {
        $this->publish();

        $component = Volt::test('play', ['slug' => $this->quiz->slug]);

        foreach ($this->fullAnswerSet() as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('next')
            ->assertHasNoErrors()
            ->assertSet('completed', true);

        $stored = QuizResponse::withoutGlobalScope('workspace')->firstOrFail();

        $this->assertSame(QuizResponse::STATUS_COMPLETED, $stored->status);

        // One stored answer per answerable type.
        $expected = count(QuestionType::cases()) - count(self::UNSUPPORTED);
        $this->assertSame($expected, $stored->answers()->count());
    }

    public function test_multiple_choice_stores_every_selection(): void
    {
        $this->publish();

        $question = $this->questions[QuestionType::MultipleChoice->value];
        $chosen = $question->options->pluck('id')->take(2)->values()->all();

        $component = Volt::test('play', ['slug' => $this->quiz->slug]);

        foreach ($this->fullAnswerSet() as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('next')->assertHasNoErrors();

        $answer = QuizAnswer::where('question_id', $question->id)->firstOrFail();

        $this->assertIsArray($answer->value, 'multiple choice must store an array');
        $this->assertCount(2, $answer->value);
        $this->assertEqualsCanonicalizing($chosen, array_map('intval', $answer->value));
    }

    public function test_multiple_choice_starts_as_an_array_so_checkboxes_bind(): void
    {
        $this->publish();

        $question = $this->questions[QuestionType::MultipleChoice->value];

        // Livewire only collects checkboxes into an array when the bound
        // property already IS an array — otherwise each box is a boolean.
        Volt::test('play', ['slug' => $this->quiz->slug])
            ->assertSet('answers.'.$question->id, []);
    }

    public function test_ranking_starts_in_the_shown_order(): void
    {
        $this->publish();

        $question = $this->questions[QuestionType::Ranking->value];

        Volt::test('play', ['slug' => $this->quiz->slug])
            ->assertSet('answers.'.$question->id, $question->options->pluck('id')->all());
    }

    public function test_address_stores_each_component(): void
    {
        $this->publish();

        $question = $this->questions[QuestionType::Address->value];

        $component = Volt::test('play', ['slug' => $this->quiz->slug]);

        foreach ($this->fullAnswerSet() as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('next')->assertHasNoErrors();

        $answer = QuizAnswer::where('question_id', $question->id)->firstOrFail();

        $this->assertSame('Leeds', $answer->value['city']);
        $this->assertSame('LS1 1AA', $answer->value['postal_code']);
    }

    public function test_matrix_stores_a_column_per_row(): void
    {
        $this->publish();

        $question = $this->questions[QuestionType::Matrix->value];
        $rowCount = count($question->settings['rows'] ?? []);

        $component = Volt::test('play', ['slug' => $this->quiz->slug]);

        foreach ($this->fullAnswerSet() as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('next')->assertHasNoErrors();

        $answer = QuizAnswer::where('question_id', $question->id)->firstOrFail();

        $this->assertCount($rowCount, $answer->value);
    }

    // ── The author's preview ──────────────────────────────────

    public function test_every_question_type_renders_on_the_preview(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('quizzes.preview', $this->quiz))->assertOk();

        foreach (QuestionType::cases() as $type) {
            $response->assertSee('Q '.$type->value);
        }
    }

    public function test_the_preview_accepts_every_type_and_stores_nothing(): void
    {
        // Draft on purpose: the preview must work before publishing.
        $this->assertSame(QuizStatus::Draft, $this->quiz->status);

        $this->actingAs($this->owner);

        $component = Volt::test('quizzes.preview', ['quiz' => $this->quiz]);

        foreach ($this->fullAnswerSet() as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('next')
            ->assertHasNoErrors()
            ->assertSet('completed', true)
            ->assertSee(__('Thank you!'));

        $this->assertSame(0, QuizResponse::withoutGlobalScope('workspace')->withTrashed()->count());
        $this->assertSame(0, QuizAnswer::count());
        $this->assertSame(0, QuizView::count());
    }

    public function test_the_preview_enforces_required_answers_like_the_player(): void
    {
        $question = $this->questions[QuestionType::ShortText->value];
        $question->update(['is_required' => true]);

        $this->actingAs($this->owner);

        Volt::test('quizzes.preview', ['quiz' => $this->quiz])
            ->call('next')
            ->assertHasErrors('answers.'.$question->id);
    }

    public function test_the_preview_multiple_choice_also_starts_as_an_array(): void
    {
        $this->actingAs($this->owner);

        $question = $this->questions[QuestionType::MultipleChoice->value];

        Volt::test('quizzes.preview', ['quiz' => $this->quiz])
            ->assertSet('answers.'.$question->id, []);
    }
}
