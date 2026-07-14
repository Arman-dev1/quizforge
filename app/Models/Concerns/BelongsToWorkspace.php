<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for workspace-owned models.
 *
 * Scopes every query to the authenticated user's current workspace and fills
 * `workspace_id` on create. Public/unauthenticated surfaces (e.g. the quiz
 * player) must query via `withoutGlobalScope('workspace')` explicitly.
 */
trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::creating(function (Model $model) {
            if (! $model->getAttribute('workspace_id') && auth()->check()) {
                $model->setAttribute('workspace_id', auth()->user()->current_workspace_id);
            }
        });

        static::addGlobalScope('workspace', function (Builder $builder) {
            if (auth()->check() && auth()->user()->current_workspace_id) {
                $builder->where(
                    $builder->getModel()->getTable().'.workspace_id',
                    auth()->user()->current_workspace_id
                );
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
