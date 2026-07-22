<?php

namespace Tests\Feature\Integrations;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function memberWorkspace(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withMember($user, $role)->create();
        $user->switchToWorkspace($workspace);

        return [$user, $workspace];
    }

    public function test_page_lists_the_email_providers(): void
    {
        [$user] = $this->memberWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee('Mailchimp')
            ->assertSee('Brevo')
            ->assertSee('MailerLite')
            ->assertSee('ActiveCampaign')
            ->assertSee('ConvertKit')
            ->assertSee(__('Popular integrations'));
    }

    public function test_admins_can_connect_and_disconnect(): void
    {
        [$user, $workspace] = $this->memberWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user);

        Volt::test('integrations.index')
            ->call('configure', 'mailchimp')
            ->set('form.api_key', 'secret-123')
            ->call('saveConnection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('workspace_integrations', [
            'workspace_id' => $workspace->id,
            'provider' => 'mailchimp',
        ]);

        // Encrypted cast round-trips the credentials.
        $integration = WorkspaceIntegration::withoutGlobalScope('workspace')
            ->where('provider', 'mailchimp')->first();
        $this->assertSame('secret-123', $integration->settings['api_key']);

        Volt::test('integrations.index')
            ->call('askDisconnect', 'mailchimp')
            ->call('disconnect');

        $this->assertDatabaseMissing('workspace_integrations', [
            'workspace_id' => $workspace->id,
            'provider' => 'mailchimp',
        ]);
    }

    public function test_connecting_requires_all_credentials(): void
    {
        [$user] = $this->memberWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user);

        Volt::test('integrations.index')
            ->call('configure', 'activecampaign')
            ->set('form.api_url', '')
            ->set('form.api_key', '')
            ->call('saveConnection')
            ->assertHasErrors(['form.api_url', 'form.api_key']);
    }

    public function test_editors_cannot_manage_integrations(): void
    {
        [$user] = $this->memberWorkspace(WorkspaceRole::Editor);

        $this->actingAs($user);

        // Editors can view the page...
        $this->get(route('integrations.index'))->assertOk();

        // ...but cannot connect.
        Volt::test('integrations.index')
            ->call('configure', 'mailchimp')
            ->assertForbidden();
    }

    public function test_search_filters_the_catalog(): void
    {
        [$user] = $this->memberWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user);

        Volt::test('integrations.index')
            ->set('search', 'convert')
            ->assertSee('ConvertKit')
            ->assertDontSee('Mailchimp');
    }

    public function test_integrations_are_scoped_to_the_workspace(): void
    {
        [$user, $workspace] = $this->memberWorkspace(WorkspaceRole::Owner);
        $mine = new WorkspaceIntegration(['provider' => 'brevo', 'settings' => ['api_key' => 'x'], 'connected_at' => now()]);
        $mine->workspace_id = $workspace->id;
        $mine->save();

        // A foreign workspace's connection must not leak in.
        $other = Workspace::factory()->create();
        $foreign = new WorkspaceIntegration(['provider' => 'mailchimp', 'settings' => ['api_key' => 'y'], 'connected_at' => now()]);
        $foreign->workspace_id = $other->id;
        $foreign->save();

        $this->actingAs($user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee(__('Connected'));

        $this->assertSame(1, WorkspaceIntegration::count());
    }
}
