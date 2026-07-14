<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    /**
     * Create a workspace, attach the user as owner, and make it their current workspace.
     */
    public function handle(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name) {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => Workspace::generateSlug($name),
                'created_by' => $user->id,
            ]);

            $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

            $user->switchToWorkspace($workspace);

            return $workspace;
        });
    }
}
