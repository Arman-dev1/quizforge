<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    protected $fillable = [
        'quiz_response_id',
        'question_id',
        'question_type',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(QuizResponse::class, 'quiz_response_id');
    }
}
