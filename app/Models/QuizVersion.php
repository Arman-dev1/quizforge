<?php

namespace App\Models;

use Database\Factories\QuizVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a quiz's content at publish time.
 * Respondents always answer against a version, never live content.
 */
class QuizVersion extends Model
{
    /** @use HasFactory<QuizVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'version',
        'content',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pages(): array
    {
        return $this->content['pages'] ?? [];
    }
}
