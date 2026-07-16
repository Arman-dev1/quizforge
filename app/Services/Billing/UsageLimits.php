<?php

namespace App\Services\Billing;

use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\Workspace;

/**
 * Resolves a workspace's plan and answers "can they do X?" questions.
 * Null limits mean unlimited. Counts intentionally bypass the tenant
 * scope so they work from any context (policies, the public player).
 */
class UsageLimits
{
    public function planKey(Workspace $workspace): string
    {
        foreach (config('plans') as $key => $plan) {
            if ($key === 'free' || empty($plan['price_id'])) {
                continue;
            }

            if ($workspace->subscribedToPrice($plan['price_id'])) {
                return $key;
            }
        }

        return 'free';
    }

    public function plan(Workspace $workspace): array
    {
        return config('plans.'.$this->planKey($workspace));
    }

    public function limit(Workspace $workspace, string $key): ?int
    {
        return $this->plan($workspace)['limits'][$key] ?? null;
    }

    // ── Counts ────────────────────────────────────────────────

    public function quizCount(Workspace $workspace): int
    {
        return Quiz::withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->count();
    }

    public function responsesThisMonth(Workspace $workspace): int
    {
        return QuizResponse::withoutGlobalScope('workspace')
            ->withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('started_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function seatCount(Workspace $workspace): int
    {
        return $workspace->members()->count() + $workspace->invitations()->count();
    }

    // ── Gates ─────────────────────────────────────────────────

    public function canCreateQuiz(Workspace $workspace): bool
    {
        $limit = $this->limit($workspace, 'quizzes');

        return $limit === null || $this->quizCount($workspace) < $limit;
    }

    public function canAddMember(Workspace $workspace): bool
    {
        $limit = $this->limit($workspace, 'members');

        return $limit === null || $this->seatCount($workspace) < $limit;
    }

    public function canAcceptResponse(Workspace $workspace): bool
    {
        $limit = $this->limit($workspace, 'responses_per_month');

        return $limit === null || $this->responsesThisMonth($workspace) < $limit;
    }

    /**
     * Usage summary for the billing page meters.
     *
     * @return array<string, array{used: int, limit: ?int}>
     */
    public function usage(Workspace $workspace): array
    {
        return [
            'quizzes' => ['used' => $this->quizCount($workspace), 'limit' => $this->limit($workspace, 'quizzes')],
            'responses_per_month' => ['used' => $this->responsesThisMonth($workspace), 'limit' => $this->limit($workspace, 'responses_per_month')],
            'members' => ['used' => $this->seatCount($workspace), 'limit' => $this->limit($workspace, 'members')],
        ];
    }
}
