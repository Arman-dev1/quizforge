<?php

namespace App\Models;

use Database\Factories\QuizPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizPage extends Model
{
    /** @use HasFactory<QuizPageFactory> */
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'title',
        'description',
        'position',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }
}
