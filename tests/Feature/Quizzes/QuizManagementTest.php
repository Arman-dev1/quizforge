<?php

namespace Tests\Feature\Quizzes;

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuizManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function editorInWorkspace(WorkspaceRole $role = WorkspaceRole::Editor): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        return [$user, $workspace];
    }

    public function test_create_page_is_displayed_to_editors(): void
    {
        [$user] = $this->editorInWorkspace();

        $this->actingAs($user)->get('/quizzes/create')->assertOk();
    }

    public function test_create_page_is_forbidden_to_viewers(): void
    {
        [$user] = $this->editorInWorkspace(WorkspaceRole::Viewer);

        $this->actingAs($user)->get('/quizzes/create')->assertForbidden();
    }

    public function test_editors_can_create_a_quiz_with_type_defaults(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();

        $this->actingAs($user);

        Volt::test('quizzes.create')
            ->call('selectType', 'lead_generation')
            ->set('name', 'Summer Campaign')
            ->call('create')
            ->assertHasNoErrors();

        $quiz = Quiz::withoutGlobalScope('workspace')->where('name', 'Summer Campaign')->first();

        $this->assertNotNull($quiz);
        $this->assertSame($workspace->id, $quiz->workspace_id);
        $this->assertSame(QuizType::LeadGeneration, $quiz->type);
        $this->assertSame(QuizStatus::Draft, $quiz->status);
        $this->assertTrue($quiz->settings['collect_leads']);
        $this->assertNotEmpty($quiz->slug);
    }

    public function test_a_quiz_type_is_required(): void
    {
        [$user] = $this->editorInWorkspace();

        $this->actingAs($user);

        Volt::test('quizzes.create')
            ->set('name', 'No Type Quiz')
            ->call('create')
            ->assertHasErrors(['type']);

        $this->assertSame(0, Quiz::withoutGlobalScope('workspace')->count());
    }

    public function test_quiz_slugs_are_globally_unique(): void
    {
        Quiz::factory()->create(['slug' => 'my-quiz']);

        $this->assertSame('my-quiz-2', Quiz::generateSlug('My Quiz'));
    }

    public function test_editors_can_rename_a_quiz(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->set('name', 'Renamed Quiz')
            ->set('description', 'Internal note')
            ->call('updateDetails')
            ->assertHasNoErrors();

        $quiz->refresh();

        $this->assertSame('Renamed Quiz', $quiz->name);
        $this->assertSame('Internal note', $quiz->description);
    }

    public function test_duplicating_a_quiz_creates_a_fresh_draft(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        $quiz = Quiz::factory()->inWorkspace($workspace)->create([
            'name' => 'Original',
            'status' => QuizStatus::Published,
            'published_at' => now(),
        ]);

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('duplicate');

        $copy = Quiz::withoutGlobalScope('workspace')->where('name', 'Original (copy)')->first();

        $this->assertNotNull($copy);
        $this->assertSame(QuizStatus::Draft, $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertNotSame($quiz->slug, $copy->slug);
        $this->assertSame($user->id, $copy->created_by);
        $this->assertSame($workspace->id, $copy->workspace_id);
    }

    public function test_quizzes_can_be_archived_and_restored(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])->call('archive');

        $quiz->refresh();
        $this->assertSame(QuizStatus::Archived, $quiz->status);
        $this->assertNotNull($quiz->archived_at);

        Volt::test('quizzes.show', ['quiz' => $quiz])->call('unarchive');

        $quiz->refresh();
        $this->assertSame(QuizStatus::Draft, $quiz->status);
        $this->assertNull($quiz->archived_at);
    }

    public function test_quizzes_must_be_archived_before_deletion(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('deleteQuiz')
            ->assertHasErrors(['actions']);

        $this->assertNull($quiz->refresh()->deleted_at);
    }

    public function test_archived_quizzes_can_be_deleted(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        $quiz = Quiz::factory()->inWorkspace($workspace)->archived()->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('deleteQuiz')
            ->assertHasNoErrors()
            ->assertRedirect(route('quizzes.index'));

        $this->assertSoftDeleted($quiz);
    }

    public function test_viewers_cannot_archive_quizzes(): void
    {
        [$user, $workspace] = $this->editorInWorkspace(WorkspaceRole::Viewer);
        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $this->actingAs($user);

        Volt::test('quizzes.show', ['quiz' => $quiz])
            ->call('archive')
            ->assertForbidden();

        $this->assertSame(QuizStatus::Draft, $quiz->refresh()->status);
    }
}
