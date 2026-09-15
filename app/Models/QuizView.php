<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Daily view counter per quiz — one row per quiz per day.
 */
class QuizView extends Model
{
    protected $fillable = [
        'quiz_id',
        'view_date',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'view_date' => 'date',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Atomic upsert — firstOrCreate + increment races against the unique
     * index on (quiz_id, view_date), which would 500 the public player on
     * the first concurrent view of a day.
     */
    public static function record(Quiz $quiz): void
    {
        $now = now();

        static::query()->upsert(
            [[
                'quiz_id' => $quiz->id,
                'view_date' => $now->toDateString(),
                'views' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['quiz_id', 'view_date'],
            ['views' => DB::raw('quiz_views.views + 1'), 'updated_at' => $now],
        );
    }
}
