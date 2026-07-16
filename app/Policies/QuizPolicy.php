<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;
use App\Services\Billing\UsageLimits;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $quiz->workspace->hasMember($user);
    }

    public function create(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace
            && ($user->roleIn($workspace)?->canEditContent() ?? false)
            && app(UsageLimits::class)->canCreateQuiz($workspace);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->roleIn($quiz->workspace)?->canEditContent() ?? false;
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }
}
