<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSettingsController;
use App\Http\Middleware\EnsureAdminMfaAvailable;
use App\Http\Middleware\EnsureMfaEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// Admin MFA (section 53). All routes 404 unless config('mfa.enabled') is true.

Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->middleware(EnsureMfaEnabled::class)
        ->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware([EnsureMfaEnabled::class, 'throttle:5,1']);
});

Route::middleware(['auth', EnsureUserIsActive::class, EnsureAdminMfaAvailable::class])->group(function () {
    Route::get('/account/two-factor', [TwoFactorSettingsController::class, 'show'])->name('two-factor.show');
    Route::post('/account/two-factor', [TwoFactorSettingsController::class, 'enable'])->middleware('throttle:5,1')->name('two-factor.enable');
    Route::post('/account/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])
        ->middleware('throttle:5,1')
        ->name('two-factor.confirm');
    Route::post('/account/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])
        ->middleware('throttle:5,1')
        ->name('two-factor.recovery-codes');
    Route::delete('/account/two-factor', [TwoFactorSettingsController::class, 'disable'])
        ->middleware('throttle:5,1')
        ->name('two-factor.disable');
});
