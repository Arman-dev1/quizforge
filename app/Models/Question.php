<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'quiz_page_id',
        'type',
        'title',
        'description',
        'placeholder',
        'help_text',
        'is_required',
        'is_hidden',
        'position',
        'settings',
        'validation',
        'logic',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'is_hidden' => 'boolean',
            'settings' => 'array',
            'validation' => 'array',
            'logic' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(QuizPage::class, 'quiz_page_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    /**
     * Duplicate this question (with options) directly below itself.
     */
    public function duplicate(): self
    {
        $copy = $this->replicate();
        $copy->position = $this->position + 1;
        $copy->save();

        foreach ($this->options as $option) {
            $copy->options()->create($option->only(['label', 'is_correct', 'position', 'settings']));
        }

        return $copy;
    }
}
