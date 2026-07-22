<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class WorkspaceIntegration extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'provider',
        'settings',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'connected_at' => 'datetime',
        ];
    }
}
