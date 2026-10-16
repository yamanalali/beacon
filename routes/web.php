<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/', function (Illuminate\Http\Request $request) {
        // Role-based landing. MFI goes to its referral inbox; technical admin goes to operations security.
        return match ($request->user()->role) {
            App\Enums\UserRole::SmeUser => redirect()->route('dashboard.sme'),
            App\Enums\UserRole::BeaconAdmin, App\Enums\UserRole::BeaconAdvisor => redirect()->route('dashboard.beacon'),
            App\Enums\UserRole::MfiUser => redirect()->route('mfi.referrals.index'),
            App\Enums\UserRole::TechnicalAdmin => redirect()->route('operations.security'),
            default => Inertia::render('Dashboard/Index'),
        };
    })->name('home');
});

foreach ([
    __DIR__.'/auth.php',
    __DIR__.'/mfa.php',
    __DIR__.'/enterprises.php',
    __DIR__.'/advisory.php',
    __DIR__.'/transactions.php',
    __DIR__.'/documents.php',
    __DIR__.'/finance.php',
    __DIR__.'/frontend.php',
    __DIR__.'/operations.php',
    __DIR__.'/administration.php',
    __DIR__.'/readiness.php',
] as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}
