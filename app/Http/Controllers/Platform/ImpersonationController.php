<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\Impersonation;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    /**
     * Drop the impersonated customer session and go back to the panel.
     *
     * Starting a session lives in the panel itself (UsersTable), where the
     * super_admin guard and CSRF are already enforced.
     */
    public function stop(Impersonation $impersonation): RedirectResponse
    {
        $impersonation->stop();

        return redirect('/super-admin');
    }
}
