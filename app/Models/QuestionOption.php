<?php

namespace App\Models;

use Database\Factories\QuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    /** @use HasFactory<QuestionOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'question_id',
        'label',
        'is_correct',
        'position',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
