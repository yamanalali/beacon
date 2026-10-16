<?php

namespace App\Http\Controllers\Administration;

use App\Ai\Support\PaginatedProps;
use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\FinancialReports\ReportExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);
        $filters = $this->filters($request);

        $logs = $this->query($filters)
            ->with('actor:id,name,email')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => $this->row($log));

        return Inertia::render('Administration/AuditLogs/Index', [
            'logs' => PaginatedProps::from($logs),
            'filters' => $filters + ['action' => '', 'actor' => '', 'target_type' => '', 'from' => '', 'to' => ''],
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'target_types' => AuditLog::query()->distinct()->orderBy('target_type')->pluck('target_type'),
        ]);
    }

    public function show(AuditLog $auditLog): Response
    {
        Gate::authorize('view', $auditLog);
        $auditLog->load('actor:id,name,email');

        return Inertia::render('Administration/AuditLogs/Show', [
            'log' => $this->row($auditLog) + [
                'context_json' => json_encode(RecordAuditLog::withoutSecrets($auditLog->context ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ]);
    }

    public function export(Request $request, ReportExporter $exporter, RecordAuditLog $audit): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);
        $filters = $this->filters($request);

        $rows = $this->query($filters)->with('actor:id,email')->orderByDesc('id')->limit(5000)->get();
        $audit->record($request->user(), 'audit_log.exported', $request->user(), ['filters' => $filters, 'rows' => $rows->count()]);

        return response()->streamDownload(function () use ($rows, $exporter): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'created_at', 'actor', 'action', 'target_type', 'target_id', 'context']);
            foreach ($rows as $log) {
                fputcsv($out, array_map([$exporter, 'safeCell'], [
                    (string) $log->id,
                    (string) $log->created_at,
                    (string) ($log->actor?->email ?? ''),
                    $log->action,
                    $log->target_type,
                    $log->target_id,
                    (string) json_encode(RecordAuditLog::withoutSecrets($log->context ?? []), JSON_UNESCAPED_UNICODE),
                ]));
            }
            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:150'],
            'target_type' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]), fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function query(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['target_type'] ?? null, fn ($q, $v) => $q->where('target_type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['actor'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->whereIn('actor_id', User::query()->where('email', 'like', $like)->orWhere('name', 'like', $like)->select('id'));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'created_at' => $log->created_at?->toDateTimeString(),
            'action' => $log->action,
            'target_type' => class_basename($log->target_type),
            'target_id' => $log->target_id,
            'actor_name' => $log->actor?->name,
            'actor_email' => $log->actor?->email,
        ];
    }
}
