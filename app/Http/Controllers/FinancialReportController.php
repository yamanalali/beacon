<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Services\FinancialReports\FinancialReportService;
use App\Services\FinancialReports\ReportExporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use App\Navigation\PageAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        // Same gate the sidebar uses (auth.can.*): only a user with an own enterprise has this page.
        Gate::authorize(PageAccess::REPORTS_ENTRY);

        return redirect()->route('enterprises.reports.show', PageAccess::ownEnterprise($request->user()));
    }

    public function show(Request $request, Enterprise $enterprise, FinancialReportService $service): Response
    {
        Gate::authorize('reports.view', $enterprise);
        [$key, $from, $to, $basis] = $this->params($request);

        return Inertia::render('FinancialReports/Show', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'report' => $service->generate($key, $enterprise, $from, $to, $basis)->toArray(),
            'filters' => ['report' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'basis' => $basis],
            'reports' => collect(FinancialReportService::REPORTS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'bases' => collect(FinancialReportService::BASES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function export(Request $request, Enterprise $enterprise, string $format, FinancialReportService $service, ReportExporter $exporter, RecordAuditLog $audit): StreamedResponse
    {
        Gate::authorize('reports.export', $enterprise);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        [$key, $from, $to, $basis] = $this->params($request);

        $report = $service->generate($key, $enterprise, $from, $to, $basis);
        $audit->record($request->user(), 'report.exported', $enterprise, [
            'report' => $key, 'format' => $format, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'basis' => $basis,
        ]);

        $filename = $key.'-'.$from->format('Ymd').'-'.$to->format('Ymd');

        return $format === 'csv' ? $exporter->csv($report, $filename) : $exporter->xlsx($report, $filename);
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable, 3: string}
     */
    private function params(Request $request): array
    {
        $data = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(FinancialReportService::REPORTS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'basis' => ['nullable', Rule::in(array_keys(FinancialReportService::BASES))],
        ]);

        // TODO: BUSINESS DECISION REQUIRED — default/allowed report periods. Default: current month.
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : CarbonImmutable::now()->startOfMonth();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::now();

        return [$data['report'] ?? 'income_statement', $from->startOfDay(), $to->startOfDay(), $data['basis'] ?? 'accrual'];
    }
}
