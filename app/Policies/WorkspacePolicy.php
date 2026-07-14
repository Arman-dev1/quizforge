<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace) === WorkspaceRole::Owner;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->canManageMembers() ?? false;
    }
}
