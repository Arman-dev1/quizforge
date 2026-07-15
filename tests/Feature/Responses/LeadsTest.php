<?php

namespace Tests\Feature\Responses;

use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function memberWithLead(string $email, bool $completed = true): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, WorkspaceRole::Editor)->create();
        $user->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();

        $factory = QuizResponse::factory();
        $response = ($completed ? $factory->completed() : $factory)->create([
            'quiz_id' => $quiz->id,
            'workspace_id' => $workspace->id,
        ]);
        $response->answers()->create(['question_id' => 1, 'question_type' => 'email', 'value' => $email]);

        return [$user, $workspace, $quiz, $response];
    }

    public function test_leads_page_lists_emails_including_partial_responses(): void
    {
        [$user, $workspace, $quiz] = $this->memberWithLead('ada@example.com');

        $partial = QuizResponse::factory()->create([
            'quiz_id' => $quiz->id,
            'workspace_id' => $workspace->id,
        ]);
        $partial->answers()->create(['question_id' => 1, 'question_type' => 'email', 'value' => 'partial@example.com']);
        $partial->answers()->create(['question_id' => 2, 'question_type' => 'phone', 'value' => '+1 555 0100']);

        // A response without an email answer is not a lead.
        QuizResponse::factory()->create(['quiz_id' => $quiz->id, 'workspace_id' => $workspace->id]);

        $this->actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('ada@example.com')
            ->assertSee('partial@example.com')
            ->assertSee('+1 555 0100');
    }

    public function test_leads_can_be_searched_by_email(): void
    {
        [$user, $workspace, $quiz] = $this->memberWithLead('ada@example.com');

        $other = QuizResponse::factory()->completed()->create([
            'quiz_id' => $quiz->id,
            'workspace_id' => $workspace->id,
        ]);
        $other->answers()->create(['question_id' => 1, 'question_type' => 'email', 'value' => 'zoe@example.com']);

        $this->actingAs($user);

        Volt::test('leads.index')
            ->set('search', 'zoe')
            ->assertSee('zoe@example.com')
            ->assertDontSee('ada@example.com');
    }

    public function test_leads_are_scoped_to_the_current_workspace(): void
    {
        [$user] = $this->memberWithLead('mine@example.com');

        // A lead in a foreign workspace.
        [$stranger] = $this->memberWithLead('foreign@example.com');

        $this->actingAs($user->refresh())
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('mine@example.com')
            ->assertDontSee('foreign@example.com');
    }
}
