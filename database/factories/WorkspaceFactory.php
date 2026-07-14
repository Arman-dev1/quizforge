<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Attach the given user as a member with the given role.
     */
    public function withMember(User $user, WorkspaceRole $role): static
    {
        return $this->afterCreating(function (Workspace $workspace) use ($user, $role) {
            $workspace->members()->attach($user->id, ['role' => $role->value]);
        });
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['created_by' => $user->id])
            ->withMember($user, WorkspaceRole::Owner);
    }
}
