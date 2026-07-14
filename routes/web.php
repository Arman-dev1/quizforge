<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/workspace', 'settings.workspace')->name('settings.workspace');
    Volt::route('settings/members', 'settings.members')->name('settings.members');

    Volt::route('workspaces/create', 'workspaces.create')->name('workspaces.create');
    Volt::route('invitations/{token}', 'invitations.accept')->name('invitations.accept');
});

require __DIR__.'/auth.php';
