<?php

namespace App\Http\Middleware;

use App\Actions\Workspaces\CreateWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees every authenticated user has a valid current workspace.
 *
 * Self-heals every path to an invalid state: first login, removal from the
 * current workspace, workspace deletion, and users created before workspaces
 * existed — by falling back to another membership or creating a personal one.
 */
class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $hasValidCurrent = $user->current_workspace_id
                && $user->workspaces()->whereKey($user->current_workspace_id)->exists();

            if (! $hasValidCurrent) {
                $fallback = $user->workspaces()->first();

                if ($fallback) {
                    $user->switchToWorkspace($fallback);
                } else {
                    app(CreateWorkspace::class)->handle($user, __(":name's Workspace", ['name' => $user->name]));
                }
            }
        }

        return $next($request);
    }
}
