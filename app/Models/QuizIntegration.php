<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizIntegration extends Model
{
    protected $fillable = [
        'provider',
        'credentials',
        'resource_id',
        'resource_name',
        'mapping',
        'status',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'mapping' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
