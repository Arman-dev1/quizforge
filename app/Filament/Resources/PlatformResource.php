<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

/**
 * Base class for every resource in the platform panel.
 *
 * Authorization here is simply "an active super admin is signed in" — the
 * panel's guard and SuperAdmin::canAccessPanel() already decide that. The
 * app's own policies (QuizPolicy, WorkspacePolicy, …) answer a different
 * question — what a *customer* may do inside their workspace — and are typed
 * for App\Models\User, so letting Filament call them with a SuperAdmin both
 * asks the wrong question and throws a TypeError.
 */
abstract class PlatformResource extends Resource
{
    protected static bool $shouldSkipAuthorization = true;
}
