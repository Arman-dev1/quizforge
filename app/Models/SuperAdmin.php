<?php

namespace App\Models;

use Database\Factories\SuperAdminFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Platform staff — the people who run QuizForge, not the people who use it.
 *
 * Deliberately a separate table and guard from App\Models\User: customer
 * accounts and staff accounts never overlap, and both can hold a session at
 * once (Laravel scopes session logins per guard), which is what lets a super
 * admin impersonate a customer without losing their own session.
 */
class SuperAdmin extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<SuperAdminFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Deactivating a staff account locks the panel without deleting history. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->trim()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $part) => Str::substr($part, 0, 1))
            ->implode('');
    }
}
