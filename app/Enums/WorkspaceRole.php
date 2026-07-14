<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => __('Full access, including deleting the workspace and billing.'),
            self::Admin => __('Manage members, settings, and all content.'),
            self::Editor => __('Create and edit quizzes, view responses.'),
            self::Viewer => __('View quizzes, responses, and analytics only.'),
        };
    }

    public function canManageWorkspace(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canEditContent(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor], true);
    }

    /**
     * Roles this role is allowed to assign to (or revoke from) other members.
     *
     * @return array<int, self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => [self::Owner, self::Admin, self::Editor, self::Viewer],
            self::Admin => [self::Admin, self::Editor, self::Viewer],
            default => [],
        };
    }

    public function canAssign(self $role): bool
    {
        return in_array($role, $this->assignableRoles(), true);
    }
}
