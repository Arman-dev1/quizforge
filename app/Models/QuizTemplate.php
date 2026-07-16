<?php

namespace App\Models;

use App\Enums\QuizType;
use Database\Factories\QuizTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable quiz blueprint. Global templates (workspace_id null) are
 * seeded system content visible to every workspace; workspace templates
 * come from "Save as template". Deliberately NOT workspace-scoped by the
 * BelongsToWorkspace trait, because global rows must stay visible.
 */
class QuizTemplate extends Model
{
    /** @use HasFactory<QuizTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'description',
        'category',
        'type',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuizType::class,
            'content' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isGlobal(): bool
    {
        return $this->workspace_id === null;
    }

    /**
     * Templates the given workspace may use: global + its own.
     */
    public function scopeAvailableTo(Builder $query, Workspace $workspace): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereNull('workspace_id')
            ->orWhere('workspace_id', $workspace->id));
    }

    public function questionCount(): int
    {
        return collect($this->content['pages'] ?? [])->sum(fn (array $page) => count($page['questions'] ?? []));
    }
}
