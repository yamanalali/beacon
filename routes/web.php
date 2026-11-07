<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/', function (Illuminate\Http\Request $request) {
    if ($request->user()) {
        // Role-based app home. MFI goes to its referral inbox; technical admin goes to operations security.
        return match ($request->user()->role) {
            App\Enums\UserRole::SmeUser => redirect()->route('dashboard.sme'),
            App\Enums\UserRole::BeaconAdmin, App\Enums\UserRole::BeaconAdvisor => redirect()->route('dashboard.beacon'),
            App\Enums\UserRole::MfiUser => redirect()->route('mfi.referrals.index'),
            App\Enums\UserRole::TechnicalAdmin => redirect()->route('operations.security'),
            default => Inertia::render('Dashboard/Index'),
        };
    }

    return Inertia::render('Landing/Home');
})->name('home');

Route::get('/about', fn () => Inertia::render('Landing/About'))->name('landing.about');
Route::get('/case-studies', [\App\Http\Controllers\Landing\CaseStudyController::class, 'index'])->name('landing.case-studies');
Route::get('/case-studies/{caseStudy}', [\App\Http\Controllers\Landing\CaseStudyController::class, 'show'])->name('landing.case-studies.show');
Route::get('/knowledge', [\App\Http\Controllers\Landing\KnowledgeController::class, 'index'])->name('landing.knowledge');
Route::get('/knowledge/{article}', [\App\Http\Controllers\Landing\KnowledgeController::class, 'show'])->name('landing.knowledge.show');
Route::get('/contact', fn () => Inertia::render('Landing/Contact'))->name('landing.contact');

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

Route::get('/offline/assets', [\App\Http\Controllers\Offline\OfflineController::class, 'assets'])->name('offline.assets');
Route::middleware('auth')->group(function () {
    Route::get('/offline', [\App\Http\Controllers\Offline\OfflineController::class, 'index'])->name('offline.index');
    Route::get('/offline/report-data/{enterprise:public_id}', [\App\Http\Controllers\Offline\OfflineController::class, 'reportData'])->name('offline.report-data');
    Route::get('/offline/session', [\App\Http\Controllers\Offline\OfflineController::class, 'session'])->name('offline.session');
});
