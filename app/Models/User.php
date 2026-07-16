<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Platform administration is a user-level flag, independent of any
     * workspace role.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * Per-type, per-channel notification defaults. Deliberately quiet:
     * everything in-app, email only for leads.
     */
    public const NOTIFICATION_DEFAULTS = [
        'new_response' => ['database' => true, 'mail' => false],
        'new_lead' => ['database' => true, 'mail' => true],
        'member_joined' => ['database' => true, 'mail' => false],
    ];

    public function wantsNotification(string $type, string $channel): bool
    {
        return (bool) ($this->notification_preferences[$type][$channel]
            ?? self::NOTIFICATION_DEFAULTS[$type][$channel]
            ?? false);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function roleIn(Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->roleOf($this);
    }

    public function switchToWorkspace(Workspace $workspace): void
    {
        $this->forceFill(['current_workspace_id' => $workspace->id])->save();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
