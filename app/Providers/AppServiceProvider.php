<?php

namespace App\Providers;

use App\Models\SuperAdmin;
use App\Services\Billing\UsageLimits;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared for the request so its plan lookups memoize instead of
        // re-querying on every limit() / feature() call.
        $this->app->scoped(UsageLimits::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Staff sign-ins are worth a timestamp — the platform panel lists it
        // so dormant accounts are easy to spot.
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'super_admin' && $event->user instanceof SuperAdmin) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
