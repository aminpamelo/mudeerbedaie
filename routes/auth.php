<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    // Dedicated Live Host sign-in surface. Shares the same auth backend
    // as `login`, but ships a BM-native, broadcast-themed UI aimed at
    // livestream hosts. Any role CAN sign in here — the post-login
    // `dashboard` route already re-routes by role, so a stray admin
    // lands on their usual dashboard.
    Volt::route('live-host/login', 'auth.live-host-login')
        ->name('live-host.login');

    // Registration disabled — accounts are created manually by admin
    // Volt::route('register', 'auth.register')
    //     ->name('register');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

    // Fallback POST routes - redirect to GET when JavaScript fails
    Route::post('login', fn () => redirect()->route('login'))->name('login.post');
    // Route::post('register', fn () => redirect()->route('register'))->name('register.post');
    Route::post('forgot-password', fn () => redirect()->route('password.request'))->name('password.request.post');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
