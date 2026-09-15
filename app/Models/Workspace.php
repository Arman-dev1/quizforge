<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Paddle\Billable;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use Billable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'created_by',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', WorkspaceRole::Owner->value);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * The workspace is the Paddle billable, but a workspace has no email of
     * its own — Cashier needs one to create the customer. Bill the owner:
     * they are the only role allowed to reach the billing page, and the
     * subscription belongs to the workspace either way.
     */
    public function billingContact(): ?User
    {
        return $this->owners()->orderBy('users.id')->first()
            ?? $this->members()->orderBy('users.id')->first();
    }

    public function paddleName(): ?string
    {
        return $this->billingContact()?->name ?? $this->name;
    }

    public function paddleEmail(): ?string
    {
        return $this->billingContact()?->email;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function roleOf(User $user): ?WorkspaceRole
    {
        $member = $this->members()->whereKey($user->id)->first();

        return $member ? WorkspaceRole::from($member->pivot->role) : null;
    }

    /**
     * Whether the given user is the only owner of this workspace.
     */
    public function isSoleOwner(User $user): bool
    {
        return $this->roleOf($user) === WorkspaceRole::Owner
            && $this->owners()->count() === 1;
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
