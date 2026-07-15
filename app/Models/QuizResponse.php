<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\QuizResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizResponse extends Model
{
    /** @use HasFactory<QuizResponseFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'workspace_id',
        'quiz_id',
        'quiz_version_id',
        'status',
        'respondent_token',
        'current_page',
        'started_at',
        'completed_at',
        'user_agent',
        'meta',
        'notes',
        'score',
        'max_score',
        'percentage',
        'passed',
        'grade',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
            'percentage' => 'float',
            'passed' => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuizVersion::class, 'quiz_version_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
