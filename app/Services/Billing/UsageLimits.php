<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\Workspace;

/**
 * Resolves a workspace's plan and answers "can they do X?" questions.
 * Limits/gates come from the super-admin-managed `plans` table when it is
 * populated, otherwise from config('plans'). Null limits mean unlimited.
 * Counts intentionally bypass the tenant scope so they work from any
 * context (policies, the public player).
 */
class UsageLimits
{
    /**
     * Per-instance memoization. Plans cannot change inside one request, so
     * resolving them repeatedly (limit(), feature(), usage()) would fire a
     * dozen identical queries per page render.
     */
    protected ?bool $managed = null;

    /** @var array<string, array<string, mixed>> */
    protected array $planCache = [];

    /** @var array<int, string> */
    protected array $planKeyCache = [];

    public function planKey(Workspace $workspace): string
    {
        return $this->planKeyCache[$workspace->id] ??= $this->resolvePlanKey($workspace);
    }

    protected function resolvePlanKey(Workspace $workspace): string
    {
        foreach ($this->paidPlans() as $key => $priceId) {
            if ($priceId && $workspace->subscribedToPrice($priceId)) {
                return $key;
            }
        }

        return 'free';
    }

    /** @return array<string, mixed> config-shaped plan (limits + flags + features) */
    public function plan(Workspace $workspace): array
    {
        return $this->planData($this->planKey($workspace));
    }

    public function limit(Workspace $workspace, string $key): ?int
    {
        return $this->plan($workspace)['limits'][$key] ?? null;
    }

    /** Whether the workspace's plan unlocks a paid feature (integrations, custom_code). */
    public function feature(Workspace $workspace, string $name): bool
    {
        return (bool) ($this->plan($workspace)['flags'][$name] ?? false);
    }

    // ── Plan source (DB when managed, else config) ────────────

    /** Whether the DB holds managed plans (memoized for the request). */
    protected function managed(): bool
    {
        return $this->managed ??= Plan::anyDefined();
    }

    /** @return array<string, ?string> key => price_id for non-free plans */
    protected function paidPlans(): array
    {
        return collect($this->allPlans())
            ->filter(fn (array $p, string $key) => $key !== 'free' && filled($p['price_id'] ?? null))
            ->map(fn (array $p) => $p['price_id'])
            ->all();
    }

    /**
     * Every available plan, config-shaped and keyed by plan key — from the
     * DB when the super-admin has defined plans, else the shipped config.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allPlans(): array
    {
        if ($this->planCache !== []) {
            return $this->planCache;
        }

        if ($this->managed()) {
            return $this->planCache = Plan::active()
                ->mapWithKeys(fn (Plan $plan) => [$plan->key => $plan->toConfigArray()])
                ->all();
        }

        return $this->planCache = collect(config('plans'))
            ->map(function (array $plan) {
                $plan['flags'] ??= [];
                $plan['features'] ??= [];

                return $plan;
            })
            ->all();
    }

    /** @return array<string, mixed> */
    protected function planData(string $key): array
    {
        $plans = $this->allPlans();

        return $plans[$key] ?? $plans['free'] ?? ['limits' => [], 'flags' => [], 'features' => []];
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

    /** Members plus invitations that can still be accepted (expired ones free their seat). */
    public function seatCount(Workspace $workspace): int
    {
        return $workspace->members()->count() + $workspace->invitations()->pending()->count();
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
