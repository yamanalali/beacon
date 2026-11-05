<?php

use App\Actions\Audit\RecordAuditLog;
use App\Dashboard\BeaconDashboardData;
use App\Dashboard\SmeDashboardData;
use App\Navigation\PageAccess;
use App\Support\TabularListExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Dashboard pages (requirements sections 38-40). Props are built server-side in app/Dashboard.
 * Programme KPIs and filters are live; §38 indicator grid stays empty until Beacon defines formulas.
 * TODO: BUSINESS DECISION REQUIRED - KPI definitions for SECTION_38 indicators, targets (§39).
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/beacon', function (Request $request, BeaconDashboardData $dashboard) {
        abort_unless(Gate::allows(PageAccess::DASHBOARD_BEACON), 403);

        $filters = $request->only(['from', 'to', 'sector', 'region', 'gender']);

        return Inertia::render('Dashboard/Beacon', [
            'indicators' => [],
            'filters' => $filters,
            'filterOptions' => $dashboard->filterOptions($request->user(), $filters),
            'exportUrl' => '/dashboard/beacon/export',
            'programme' => $dashboard->build($request->user(), $filters),
        ]);
    })->name('dashboard.beacon');

    Route::get('/dashboard/beacon/export/{format}', function (Request $request, string $format, BeaconDashboardData $dashboard, TabularListExporter $exporter, RecordAuditLog $audit) {
        abort_unless(Gate::allows(PageAccess::DASHBOARD_BEACON), 403);
        abort_unless($format === 'csv', 404);

        $filters = $request->only(['from', 'to', 'sector', 'region', 'gender']);
        [$headers, $rows] = $dashboard->exportRows($request->user(), $filters);

        $audit->record($request->user(), 'dashboard.beacon.exported', $request->user(), [
            'filters' => $dashboard->normalizeFilters($filters),
            'format' => $format,
        ]);

        return $exporter->csv($headers, $rows, 'beacon-programme-'.now()->format('Ymd'));
    })->whereIn('format', ['csv'])->name('dashboard.beacon.export');

    Route::get('/dashboard/sme', function (Request $request) {
        abort_unless(Gate::allows(PageAccess::DASHBOARD_SME), 403);

        return Inertia::render('Dashboard/Sme', app(SmeDashboardData::class)->build($request->user()));
    })->name('dashboard.sme');
});
