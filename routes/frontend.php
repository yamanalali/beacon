<?php

use App\Dashboard\BeaconDashboardData;
use App\Dashboard\SmeDashboardData;
use App\Navigation\PageAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Dashboard pages (requirements sections 38-40). Props are built server-side in app/Dashboard.
 * TODO: BUSINESS DECISION REQUIRED - KPI definitions, periods, filters and formulas (section 39);
 * which Beacon roles see the programme dashboard and whether advisors are scoped to assigned SMEs.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/beacon', function (Request $request) {
        abort_unless(Gate::allows(PageAccess::DASHBOARD_BEACON), 403);

        return Inertia::render('Dashboard/Beacon', [
            // TODO: BUSINESS DECISION REQUIRED - section 39 indicator definitions; structured KPIs are in `programme`.
            'indicators' => [],
            'filters' => $request->only(['from', 'to', 'sector', 'region', 'gender']),
            'filterOptions' => ['sectors' => [], 'regions' => [], 'genders' => []],
            'exportUrl' => null,
            'programme' => app(BeaconDashboardData::class)->build($request->user()),
        ]);
    })->name('dashboard.beacon');

    Route::get('/dashboard/sme', function (Request $request) {
        abort_unless(Gate::allows(PageAccess::DASHBOARD_SME), 403);

        return Inertia::render('Dashboard/Sme', app(SmeDashboardData::class)->build($request->user()));
    })->name('dashboard.sme');
});