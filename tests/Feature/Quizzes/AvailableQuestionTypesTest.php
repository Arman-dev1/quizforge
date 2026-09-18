<?php

namespace Tests\Feature\Quizzes;

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
 * Only question types the player can actually render may be offered.
 *
 * File upload and signature draw a "coming soon" placeholder instead of a
 * working control. Offering them in the builder lets an author publish a
 * question no respondent can answer — and advertises functionality that is
 * not there. The enum cases remain so stored quizzes keep resolving.
 */
class AvailableQuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    protected const UNRELEASED = [QuestionType::FileUpload, QuestionType::Signature];

    /** @return array{0: User, 1: Quiz, 2: QuizPage} */
    protected function quizWithPage(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withMember($owner, WorkspaceRole::Owner)->create();
        $owner->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();
        $page = QuizPage::factory()->for($quiz)->create(['position' => 0]);

        return [$owner, $quiz->refresh(), $page];
    }

    public function test_unreleased_types_are_not_offered_in_the_picker(): void
    {
        $offered = collect(QuestionType::grouped())->flatten();

        foreach (self::UNRELEASED as $type) {
            $this->assertFalse(
                $offered->contains($type),
                "{$type->value} is still offered in the builder but the player cannot render it.",
            );
        }
    }

    public function test_every_offered_type_is_available(): void
    {
        foreach (collect(QuestionType::grouped())->flatten() as $type) {
            $this->assertTrue($type->isAvailable(), "{$type->value} is offered but marked unavailable.");
        }
    }

    public function test_the_picker_still_offers_a_useful_range(): void
    {
        $offered = collect(QuestionType::grouped())->flatten();

        // Guards against a filter bug quietly emptying the builder.
        $this->assertGreaterThanOrEqual(20, $offered->count());
        $this->assertTrue($offered->contains(QuestionType::SingleChoice));
        $this->assertTrue($offered->contains(QuestionType::Matrix));
    }

    public function test_adding_an_unreleased_type_is_refused(): void
    {
        [$owner, $quiz, $page] = $this->quizWithPage();

        $this->actingAs($owner);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $page->id)
            ->call('addQuestion', QuestionType::FileUpload->value)
            ->assertStatus(422);

        $this->assertSame(0, $page->questions()->count());
    }

    public function test_switching_an_existing_question_to_an_unreleased_type_is_refused(): void
    {
        [$owner, $quiz, $page] = $this->quizWithPage();

        $question = $page->questions()->create([
            'quiz_id' => $quiz->id,
            'type' => QuestionType::ShortText,
            'title' => 'Your name',
            'position' => 0,
        ]);

        $this->actingAs($owner);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('selectQuestion', $question->id)
            ->call('changeType', QuestionType::Signature->value)
            ->assertStatus(422);

        $this->assertSame(QuestionType::ShortText, $question->refresh()->type);
    }

    public function test_an_available_type_can_still_be_added(): void
    {
        [$owner, $quiz, $page] = $this->quizWithPage();

        $this->actingAs($owner);

        Volt::test('quizzes.builder', ['quiz' => $quiz])
            ->call('startPicking', $page->id)
            ->call('addQuestion', QuestionType::SingleChoice->value)
            ->assertHasNoErrors();

        $this->assertSame(1, $page->questions()->count());
    }
}
