<?php

namespace Tests\Feature\Quizzes;

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuizIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function editorInWorkspace(WorkspaceRole $role = WorkspaceRole::Editor): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        return [$user, $workspace];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/quizzes')->assertRedirect(route('login'));
    }

    public function test_index_lists_quizzes_of_the_current_workspace(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'My Own Quiz']);

        $this->actingAs($user)
            ->get('/quizzes')
            ->assertOk()
            ->assertSee('My Own Quiz');
    }

    public function test_index_does_not_show_quizzes_from_other_workspaces(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'Visible Quiz']);
        Quiz::factory()->create(['name' => 'Foreign Quiz']);

        $this->actingAs($user)
            ->get('/quizzes')
            ->assertOk()
            ->assertSee('Visible Quiz')
            ->assertDontSee('Foreign Quiz');
    }

    public function test_quizzes_from_other_workspaces_cannot_be_opened(): void
    {
        [$user] = $this->editorInWorkspace();
        $foreign = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.show', $foreign))
            ->assertNotFound();
    }

    public function test_search_filters_the_list(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'Alpha Onboarding']);
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'Beta Feedback']);

        $this->actingAs($user);

        Volt::test('quizzes.index')
            ->set('search', 'Alpha')
            ->assertSee('Alpha Onboarding')
            ->assertDontSee('Beta Feedback');
    }

    public function test_status_filter_narrows_the_list(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'Draft Quiz']);
        Quiz::factory()->inWorkspace($workspace)->archived()->create(['name' => 'Archived Quiz']);

        $this->actingAs($user);

        Volt::test('quizzes.index')
            ->set('status', QuizStatus::Archived->value)
            ->assertSee('Archived Quiz')
            ->assertDontSee('Draft Quiz');
    }

    public function test_type_filter_narrows_the_list(): void
    {
        [$user, $workspace] = $this->editorInWorkspace();
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'Poll Quiz', 'type' => QuizType::Poll]);
        Quiz::factory()->inWorkspace($workspace)->create(['name' => 'Exam Quiz', 'type' => QuizType::Exam]);

        $this->actingAs($user);

        Volt::test('quizzes.index')
            ->set('type', QuizType::Poll->value)
            ->assertSee('Poll Quiz')
            ->assertDontSee('Exam Quiz');
    }

    public function test_index_actions_cannot_touch_foreign_quizzes(): void
    {
        [$user] = $this->editorInWorkspace();
        $foreign = Quiz::factory()->create();

        $this->actingAs($user);

        try {
            Volt::test('quizzes.index')->call('archive', $foreign->id);
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            // Foreign quizzes are invisible to this workspace, as intended.
        }

        $this->assertSame(QuizStatus::Draft, $foreign->refresh()->status);
    }

    public function test_empty_state_is_shown_when_there_are_no_quizzes(): void
    {
        [$user] = $this->editorInWorkspace();

        $this->actingAs($user)
            ->get('/quizzes')
            ->assertOk()
            ->assertSee(__('Create your first quiz'));
    }
}
