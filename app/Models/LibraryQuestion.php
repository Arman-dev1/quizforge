<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\LibraryQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable question saved from the builder, scoped to its workspace.
 */
class LibraryQuestion extends Model
{
    /** @use HasFactory<LibraryQuestionFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'question',
    ];

    protected function casts(): array
    {
        return [
            'question' => 'array',
        ];
    }
}
