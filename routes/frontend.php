<?php

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Dashboard pages (requirements sections 38-40). Props are stubs: no KPI is calculated here.
 * TODO: BUSINESS DECISION REQUIRED - KPI definitions, periods, filters and formulas (section 39);
 * which Beacon roles see the programme dashboard and whether advisors are scoped to assigned SMEs.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/beacon', function (Request $request) {
        abort_unless(in_array($request->user()->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true), 403);

        return Inertia::render('Dashboard/Beacon', [
            'indicators' => [],
            'filters' => $request->only(['from', 'to', 'sector', 'region', 'gender']),
            'filterOptions' => ['sectors' => [], 'regions' => [], 'genders' => []],
            'exportUrl' => null,
        ]);
    })->name('dashboard.beacon');

    Route::get('/dashboard/sme', function (Request $request) {
        abort_unless($request->user()->role === UserRole::SmeUser, 403);

        return Inertia::render('Dashboard/Sme', [
            'recentActivity' => [],
            'outstandingActions' => [],
            'upcomingDeadlines' => [],
            'readinessStatus' => null,
            'referralStatus' => null,
        ]);
    })->name('dashboard.sme');
});
