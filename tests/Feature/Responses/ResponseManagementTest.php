<?php

namespace Tests\Feature\Responses;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizPage;
use App\Models\QuizResponse;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ResponseManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published quiz (email + single choice) with one completed and
     * one partial response.
     */
    protected function quizWithResponses(WorkspaceRole $role = WorkspaceRole::Editor): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();
        $page = QuizPage::factory()->for($quiz)->create();

        $email = $page->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::Email,
            'title' => 'Your email', 'position' => 0,
        ]);
        $choice = $page->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::SingleChoice,
            'title' => 'Favourite colour', 'position' => 1,
        ]);
        $red = $choice->options()->create(['label' => 'Red', 'position' => 0]);
        $choice->options()->create(['label' => 'Blue', 'position' => 1]);

        $owner = $workspace->owners()->first() ?? $user;
        app(PublishQuiz::class)->handle($quiz, $owner);
        $version = $quiz->refresh()->latestVersion();

        $completed = QuizResponse::factory()->completed()->create([
            'quiz_id' => $quiz->id,
            'workspace_id' => $workspace->id,
            'quiz_version_id' => $version->id,
        ]);
        $completed->answers()->create(['question_id' => $email->id, 'question_type' => 'email', 'value' => 'ada@example.com']);
        $completed->answers()->create(['question_id' => $choice->id, 'question_type' => 'single_choice', 'value' => $red->id]);

        $partial = QuizResponse::factory()->create([
            'quiz_id' => $quiz->id,
            'workspace_id' => $workspace->id,
            'quiz_version_id' => $version->id,
        ]);
        $partial->answers()->create(['question_id' => $email->id, 'question_type' => 'email', 'value' => 'bob@example.com']);

        return [$user, $quiz, $completed, $partial];
    }

    public function test_the_responses_index_lists_responses_with_contacts(): void
    {
        [$user, $quiz] = $this->quizWithResponses();

        $this->actingAs($user)
            ->get(route('quizzes.responses', $quiz))
            ->assertOk()
            ->assertSee('ada@example.com')
            ->assertSee('bob@example.com');
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        [$user, $quiz] = $this->quizWithResponses();

        $this->actingAs($user);

        Volt::test('quizzes.responses', ['quiz' => $quiz])
            ->set('status', 'completed')
            ->assertSee('ada@example.com')
            ->assertDontSee('bob@example.com');
    }

    public function test_editors_can_trash_and_restore_responses(): void
    {
        [$user, $quiz, $completed] = $this->quizWithResponses();

        $this->actingAs($user);

        $component = Volt::test('quizzes.responses', ['quiz' => $quiz])
            ->call('deleteResponse', $completed->id);

        $this->assertSoftDeleted($completed);

        $component->set('status', 'trashed')
            ->assertSee('ada@example.com')
            ->call('restoreResponse', $completed->id);

        $this->assertNull($completed->refresh()->deleted_at);
    }

    public function test_viewers_cannot_delete_responses(): void
    {
        [$user, $quiz, $completed] = $this->quizWithResponses(WorkspaceRole::Viewer);

        $this->actingAs($user);

        Volt::test('quizzes.responses', ['quiz' => $quiz])
            ->call('deleteResponse', $completed->id)
            ->assertForbidden();

        $this->assertNull($completed->refresh()->deleted_at);
    }

    public function test_the_detail_page_formats_answers_with_option_labels(): void
    {
        [$user, $quiz, $completed] = $this->quizWithResponses();

        $this->actingAs($user)
            ->get(route('quizzes.responses.show', [$quiz, $completed->id]))
            ->assertOk()
            ->assertSee('Favourite colour')
            ->assertSee('Red')
            ->assertSee('ada@example.com');
    }

    public function test_editors_can_save_internal_notes(): void
    {
        [$user, $quiz, $completed] = $this->quizWithResponses();

        $this->actingAs($user);

        Volt::test('quizzes.response-detail', ['quiz' => $quiz, 'response' => $completed->id])
            ->set('notes', 'Follow up next week')
            ->assertHasNoErrors();

        $this->assertSame('Follow up next week', $completed->refresh()->notes);
    }

    public function test_responses_of_other_workspaces_are_not_reachable(): void
    {
        [$user] = $this->quizWithResponses();

        $foreignQuiz = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.responses', $foreignQuiz))
            ->assertNotFound();
    }

    public function test_a_response_must_belong_to_the_route_quiz(): void
    {
        [$user, $quiz, $completed] = $this->quizWithResponses();

        $otherQuiz = Quiz::factory()->inWorkspace($completed->quiz->workspace)->create();

        $this->actingAs($user)
            ->get(route('quizzes.responses.show', [$otherQuiz, $completed->id]))
            ->assertNotFound();
    }

    public function test_csv_export_contains_headers_and_formatted_answers(): void
    {
        [$user, $quiz] = $this->quizWithResponses();

        $response = $this->actingAs($user)->get(route('quizzes.responses.export', $quiz));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Favourite colour', $csv);
        $this->assertStringContainsString('ada@example.com', $csv);
        $this->assertStringContainsString('Red', $csv);
        $this->assertStringContainsString('completed', $csv);
        $this->assertStringContainsString('in_progress', $csv);
    }

    public function test_csv_export_is_not_available_across_workspaces(): void
    {
        [$user] = $this->quizWithResponses();

        $foreignQuiz = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.responses.export', $foreignQuiz))
            ->assertNotFound();
    }
}
