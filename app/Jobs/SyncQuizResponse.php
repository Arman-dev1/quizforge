<?php

namespace App\Jobs;

use App\Models\QuizIntegration;
use App\Models\QuizResponse;
use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncQuizResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $integrationId,
        public int $responseId,
    ) {}

    public function handle(IntegrationManager $manager): void
    {
        $integration = QuizIntegration::find($this->integrationId);

        if (! $integration || ! $integration->isConnected()) {
            return;
        }

        $response = QuizResponse::withoutGlobalScope('workspace')
            ->with('answers')
            ->find($this->responseId);

        if (! $response) {
            return;
        }

        $answers = $response->answers->keyBy('question_id');
        $data = [];

        foreach ((array) $integration->mapping as $field => $questionId) {
            if (! $questionId) {
                continue;
            }

            $value = $answers[$questionId]->value ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $data[$field] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        // Email is the one field every provider needs.
        if (empty($data['email'])) {
            return;
        }

        try {
            $manager->driver($integration->provider)->sync(
                $integration->credentials,
                (string) $integration->resource_id,
                $data,
            );

            $integration->forceFill(['last_synced_at' => now()])->save();
        } catch (IntegrationException $e) {
            Log::warning('Integration sync failed', [
                'provider' => $integration->provider,
                'quiz_id' => $integration->quiz_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
