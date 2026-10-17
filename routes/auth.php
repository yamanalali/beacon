<?php

use App\Http\Controllers\Administration\InvitationController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\UserAccountController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'accept'])->where('token', '[A-Za-z0-9]{64}')->name('invitations.accept');
    Route::post('/invitations/{token}', [InvitationController::class, 'register'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:5,1')->name('invitations.register');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    Route::middleware('role:beacon_admin')->group(function () {
        Route::get('/users', [UserAccountController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserAccountController::class, 'create'])->name('users.create');
        Route::post('/users', [UserAccountController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/deactivate', [UserAccountController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/activate', [UserAccountController::class, 'activate'])->name('users.activate');
    });
});
