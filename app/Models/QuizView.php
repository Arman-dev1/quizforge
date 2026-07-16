<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public static function record(Quiz $quiz): void
    {
        static::firstOrCreate([
            'quiz_id' => $quiz->id,
            'view_date' => now()->startOfDay(),
        ])->increment('views');
    }
}
