<?php

use App\Http\Controllers\InitialPasswordController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')
        ->name('register');

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    // Second step of every sign-in: the code sent by email (ADR 0004).
    Volt::route('login/code', 'pages.auth.login-code')
        ->middleware('throttle:30,1')
        ->name('login.code');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('change-initial-password', [InitialPasswordController::class, 'edit'])->name('password.initial');
    Route::post('change-initial-password', [InitialPasswordController::class, 'update'])->name('password.initial.update');

    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
