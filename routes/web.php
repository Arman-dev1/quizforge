<?php

use App\Http\Controllers\ContentPageController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\ResponseExportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// A view route (not a closure) so `route:cache` works in production.
Route::view('/', 'welcome')->name('home');

// Public player. `/quiz/{slug}` is the canonical address; the old `/q/{slug}`
// stays as a permanent redirect so links already shared keep working.
Volt::route('quiz/{slug}', 'play')
    ->middleware('throttle:60,1')
    ->name('quiz.play');

Route::permanentRedirect('q/{slug}', 'quiz/{slug}');

// Public documents managed from the platform panel: about, terms, privacy,
// refunds, integrations. A single route rather than one per document, so an
// owner adding a page does not need a code change.
Route::get('page/{slug}', [ContentPageController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('page.show');

// Ending an impersonated session. Starting one happens inside the panel
// (a CSRF-protected Livewire action); this only ever drops a session, so it
// is safe to expose to whoever currently holds it.
Route::post('platform/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->name('platform.impersonate.stop');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Account pages stay reachable without a verified email — this is where
// someone fixes the address they mistyped at registration.
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// Everything that creates content, spends quota, or touches other people
// requires a verified email.
Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('settings/workspace', 'settings.workspace')->name('settings.workspace');
    Volt::route('settings/members', 'settings.members')->name('settings.members');
    Volt::route('settings/notifications', 'settings.notifications')->name('settings.notifications');
    Volt::route('settings/billing', 'settings.billing')->name('settings.billing');

    Volt::route('workspaces/create', 'workspaces.create')->name('workspaces.create');
    Volt::route('invitations/{token}', 'invitations.accept')->name('invitations.accept');

    Volt::route('quizzes', 'quizzes.index')->name('quizzes.index');
    Volt::route('quizzes/create', 'quizzes.create')->name('quizzes.create');
    Volt::route('quizzes/{quiz}', 'quizzes.show')->name('quizzes.show');
    Volt::route('quizzes/{quiz}/builder', 'quizzes.builder')->name('quizzes.builder');
    Volt::route('quizzes/{quiz}/preview', 'quizzes.preview')->name('quizzes.preview');
    Volt::route('quizzes/{quiz}/design', 'quizzes.design')->name('quizzes.design');
    Volt::route('quizzes/{quiz}/results', 'quizzes.results')->name('quizzes.results');

    Volt::route('quizzes/{quiz}/integrations', 'quizzes.integrations')->name('quizzes.integrations');
    Volt::route('quizzes/{quiz}/responses', 'quizzes.responses')->name('quizzes.responses');
    Route::get('quizzes/{quiz}/responses/export', ResponseExportController::class)->name('quizzes.responses.export');
    Volt::route('quizzes/{quiz}/responses/{response}', 'quizzes.response-detail')->name('quizzes.responses.show');

    Volt::route('quizzes/{quiz}/analytics', 'quizzes.analytics')->name('quizzes.analytics');

    Volt::route('leads', 'leads.index')->name('leads.index');
});

require __DIR__.'/auth.php';
