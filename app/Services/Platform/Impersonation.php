<?php

namespace App\Services\Platform;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Log in as this client" for platform staff.
 *
 * The super admin keeps their own session on the `super_admin` guard; we only
 * add a customer session on the `web` guard alongside it. Stopping simply
 * drops the customer session, so staff never have to log back in.
 *
 * Every start and stop is logged: acting as someone else's account is exactly
 * the kind of access that has to leave a trail.
 */
class Impersonation
{
    /** Session key holding the id of the staff member who started this. */
    public const SESSION_KEY = 'impersonation.super_admin_id';

    public function start(SuperAdmin $admin, User $user): void
    {
        // Never nest: stop any session already in progress first.
        $this->stop();

        // login() migrates the session id itself, so the marker goes in
        // afterwards — session data survives the migration, the id does not.
        Auth::guard('web')->login($user);

        session()->put(self::SESSION_KEY, $admin->id);

        Log::info('Impersonation started', [
            'super_admin_id' => $admin->id,
            'super_admin_email' => $admin->email,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);
    }

    public function stop(): void
    {
        if (! $this->isActive()) {
            return;
        }

        Log::info('Impersonation ended', [
            'super_admin_id' => session(self::SESSION_KEY),
            'user_id' => Auth::guard('web')->id(),
        ]);

        Auth::guard('web')->logout();

        session()->forget(self::SESSION_KEY);
    }

    public function isActive(): bool
    {
        return session()->has(self::SESSION_KEY) && Auth::guard('web')->check();
    }

    /** The staff member behind the current impersonated session, if any. */
    public function impersonator(): ?SuperAdmin
    {
        if (! $this->isActive()) {
            return null;
        }

        return SuperAdmin::find(session(self::SESSION_KEY));
    }
}
