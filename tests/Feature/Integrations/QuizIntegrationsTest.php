<?php

namespace Tests\Feature\Integrations;

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Enums\WorkspaceRole;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizIntegration;
use App\Models\QuizPage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuizIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Integrations are a paid feature; enable it for the functionality tests.
        config(['plans.free.flags.integrations' => true]);
    }

    /** @return array{0: User, 1: Quiz, 2: Question} */
    protected function quizWithEmailQuestion(WorkspaceRole $role = WorkspaceRole::Editor): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        $quiz = Quiz::factory()->inWorkspace($workspace)->create();
        $page = QuizPage::factory()->for($quiz)->create();
        $email = $page->questions()->create([
            'quiz_id' => $quiz->id, 'type' => QuestionType::Email,
            'title' => 'Your email', 'is_required' => true, 'position' => 0,
        ]);

        return [$user, $quiz, $email];
    }

    protected function fakeMailchimpOk(): void
    {
        Http::fake([
            '*/3.0/lists/*/members/*' => Http::response([], 200),
            '*/3.0/lists*' => Http::response(['lists' => [['id' => 'aud1', 'name' => 'Main Audience']]], 200),
            '*/3.0/ping*' => Http::response(['health_status' => 'Everything is Chimpy!'], 200),
        ]);
    }

    public function test_page_lists_the_providers(): void
    {
        [$user, $quiz] = $this->quizWithEmailQuestion();

        $this->actingAs($user)
            ->get(route('quizzes.integrations', $quiz))
            ->assertOk()
            ->assertSee('Mailchimp')
            ->assertSee('ActiveCampaign');
    }

    public function test_verify_connect_and_map_flow(): void
    {
        $this->fakeMailchimpOk();
        [$user, $quiz, $email] = $this->quizWithEmailQuestion();

        $this->actingAs($user);

        $component = Volt::test('quizzes.integrations', ['quiz' => $quiz])
            ->call('startSetup', 'mailchimp')
            ->set('credentials.api_key', 'abc-us21')
            ->call('verify')
            ->assertSet('step', 2)
            ->assertSet('verifyError', '');

        // Resource was fetched from the provider.
        $component->set('resourceId', 'aud1')
            ->call('chooseResource')
            ->assertSet('step', 3)
            // email auto-mapped to the email question
            ->assertSet('mapping.email', (string) $email->id)
            ->call('saveMapping')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('quiz_integrations', [
            'quiz_id' => $quiz->id,
            'provider' => 'mailchimp',
            'resource_id' => 'aud1',
            'status' => 'connected',
        ]);
    }

    public function test_verify_rejects_bad_credentials(): void
    {
        Http::fake(['*/3.0/ping*' => Http::response([], 401)]);
        [$user, $quiz] = $this->quizWithEmailQuestion();

        $this->actingAs($user);

        Volt::test('quizzes.integrations', ['quiz' => $quiz])
            ->call('startSetup', 'mailchimp')
            ->set('credentials.api_key', 'wrong-us21')
            ->call('verify')
            ->assertSet('step', 1)
            ->assertSet('verifyError', __('The credentials were rejected. Double-check them and try again.'));

        $this->assertDatabaseCount('quiz_integrations', 0);
    }

    public function test_saving_requires_an_email_mapping(): void
    {
        $this->fakeMailchimpOk();
        [$user, $quiz] = $this->quizWithEmailQuestion();

        $this->actingAs($user);

        Volt::test('quizzes.integrations', ['quiz' => $quiz])
            ->call('startSetup', 'mailchimp')
            ->set('credentials.api_key', 'abc-us21')
            ->call('verify')
            ->set('resourceId', 'aud1')
            ->call('chooseResource')
            ->set('mapping.email', '')
            ->call('saveMapping')
            ->assertHasErrors(['mapping.email']);
    }

    public function test_disconnect_removes_the_integration(): void
    {
        [$user, $quiz, $email] = $this->quizWithEmailQuestion();
        $quiz->integrations()->create([
            'provider' => 'mailchimp',
            'credentials' => ['api_key' => 'abc-us21'],
            'resource_id' => 'aud1',
            'resource_name' => 'Main',
            'mapping' => ['email' => (string) $email->id],
            'status' => 'connected',
        ]);

        $this->actingAs($user);

        Volt::test('quizzes.integrations', ['quiz' => $quiz])
            ->call('askDisconnect', 'mailchimp')
            ->call('disconnect');

        $this->assertDatabaseCount('quiz_integrations', 0);
    }

    public function test_viewers_cannot_manage_integrations(): void
    {
        [$user, $quiz] = $this->quizWithEmailQuestion(WorkspaceRole::Viewer);

        $this->actingAs($user);

        Volt::test('quizzes.integrations', ['quiz' => $quiz])
            ->call('startSetup', 'mailchimp')
            ->assertForbidden();
    }

    public function test_completed_response_syncs_to_the_connected_integration(): void
    {
        config(['queue.default' => 'sync']);
        $this->fakeMailchimpOk();

        [$user, $quiz, $email] = $this->quizWithEmailQuestion();
        $quiz->integrations()->create([
            'provider' => 'mailchimp',
            'credentials' => ['api_key' => 'abc-us21'],
            'resource_id' => 'aud1',
            'resource_name' => 'Main Audience',
            'mapping' => ['email' => (string) $email->id],
            'status' => 'connected',
        ]);

        app(PublishQuiz::class)->handle($quiz, $user);

        Volt::test('play', ['slug' => $quiz->refresh()->slug])
            ->set("answers.{$email->id}", 'lead@example.com')
            ->call('next')
            ->assertSet('completed', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/members/')
            && ($request['email_address'] ?? null) === 'lead@example.com');

        $this->assertNotNull(QuizIntegration::first()->last_synced_at);
    }

    public function test_integrations_are_locked_on_the_free_plan(): void
    {
        config(['plans.free.flags.integrations' => false]);
        [$user, $quiz] = $this->quizWithEmailQuestion();

        $this->actingAs($user)
            ->get(route('quizzes.integrations', $quiz))
            ->assertOk()
            ->assertSee(__('Integrations are a paid feature'));

        // The connect flow is blocked without the paid feature.
        Volt::test('quizzes.integrations', ['quiz' => $quiz])
            ->call('startSetup', 'mailchimp')
            ->assertForbidden();
    }

    public function test_integrations_page_is_scoped_to_the_workspace(): void
    {
        [$user] = $this->quizWithEmailQuestion();
        $foreign = Quiz::factory()->create();

        $this->actingAs($user)
            ->get(route('quizzes.integrations', $foreign))
            ->assertNotFound();
    }
}
