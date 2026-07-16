<?php

use App\Http\Controllers\ResponseExportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('q/{slug}', 'play')
    ->middleware('throttle:60,1')
    ->name('quiz.play');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
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

    Volt::route('quizzes/{quiz}/responses', 'quizzes.responses')->name('quizzes.responses');
    Route::get('quizzes/{quiz}/responses/export', ResponseExportController::class)->name('quizzes.responses.export');
    Volt::route('quizzes/{quiz}/responses/{response}', 'quizzes.response-detail')->name('quizzes.responses.show');

    Volt::route('quizzes/{quiz}/analytics', 'quizzes.analytics')->name('quizzes.analytics');

    Volt::route('leads', 'leads.index')->name('leads.index');
});

require __DIR__.'/auth.php';
