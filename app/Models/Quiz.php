<?php

namespace App\Models;

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'slug',
        'type',
        'status',
        'description',
        'settings',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuizType::class,
            'status' => QuizStatus::class,
            'settings' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(QuizPage::class)->orderBy('position');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function isArchived(): bool
    {
        return $this->status === QuizStatus::Archived;
    }

    public function archive(): void
    {
        $this->update([
            'status' => QuizStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function unarchive(): void
    {
        $this->update([
            'status' => QuizStatus::Draft,
            'archived_at' => null,
        ]);
    }

    /**
     * Duplicate this quiz as a fresh draft owned by the given user.
     */
    public function duplicate(User $user): self
    {
        $copy = $this->replicate(['slug', 'status', 'published_at', 'archived_at', 'created_by']);

        $copy->name = Str::limit($this->name, 140, '').' (copy)';
        $copy->slug = static::generateSlug($copy->name);
        $copy->status = QuizStatus::Draft;
        $copy->created_by = $user->id;
        $copy->save();

        return $copy;
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug(Str::limit($name, 60, '')) ?: 'quiz';
        $slug = $base;
        $suffix = 1;

        while (static::withoutGlobalScope('workspace')->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
