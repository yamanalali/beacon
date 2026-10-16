<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Audit\RecordAuditLog;
use App\Navigation\PageAccess;
use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\Operations\BackupService;
use App\Services\Operations\SecurityChecklist;
use App\Support\DataControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sections 53, 54, 56 operational screens.
 * TECHNICAL_ADMIN manages the technical environment (checklist, backups, restore tests) without gaining
 * access to business records: these screens show metadata only, never backup contents.
 * TODO: BUSINESS DECISION REQUIRED - whether BEACON_ADMIN should also see backup status / trigger backups (currently technical admin only).
 */
class OperationsController extends Controller
{
    public function security(Request $request, SecurityChecklist $checklist): Response
    {
        $this->technicalAdmin($request);

        return Inertia::render('Operations/Security', ['items' => $checklist->items()]);
    }

    public function backups(Request $request): Response
    {
        $this->technicalAdmin($request);

        return Inertia::render('Operations/Backups', [
            'config' => [
                'enabled' => (bool) config('operations.backup.enabled'),
                'time' => config('operations.backup.time'),
                'retention_days' => config('operations.backup.retention_days'),
                'separate_key' => config('operations.backup.key') !== null,
            ],
            'runs' => BackupRun::query()->with('trigger:id,name')->latest('id')->limit(50)->get()->map(fn (BackupRun $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'type_label' => BackupRun::TYPE_LABELS[$r->type] ?? $r->type,
                'status' => $r->status,
                'status_label' => BackupRun::STATUS_LABELS[$r->status] ?? $r->status,
                'backup_run_id' => $r->backup_run_id,
                'size_bytes' => $r->size_bytes,
                'details' => $r->details,
                'error' => $r->error,
                'triggered_by' => $r->trigger?->name ?? 'المجدول',
                'finished_at' => $r->finished_at?->toDateTimeString(),
                'pruned' => $r->pruned_at !== null,
            ]),
        ]);
    }

    public function runBackup(Request $request, BackupService $service, RecordAuditLog $audit): RedirectResponse
    {
        $actor = $this->technicalAdmin($request);
        $run = $service->backup($actor);
        $audit->record($actor, 'operations.backup_run', $run, ['status' => $run->status]);

        return redirect()->route('operations.backups')->with($run->status === BackupRun::STATUS_SUCCEEDED ? 'success' : 'error',
            $run->status === BackupRun::STATUS_SUCCEEDED ? 'تم إنشاء النسخة الاحتياطية.' : 'فشل إنشاء النسخة الاحتياطية.');
    }

    public function restoreTest(Request $request, BackupRun $backupRun, BackupService $service, RecordAuditLog $audit): RedirectResponse
    {
        $actor = $this->technicalAdmin($request);
        abort_unless($backupRun->type === BackupRun::TYPE_BACKUP, 404);
        $run = $service->restoreTest($backupRun, $actor);
        $audit->record($actor, 'operations.restore_test_run', $run, ['backup_run_id' => $backupRun->id, 'status' => $run->status]);

        return redirect()->route('operations.backups');
    }

    public function dataControl(Request $request): Response
    {
        $user = $request->user();
        abort_unless(Gate::forUser($user)->allows(PageAccess::OPERATIONS_DATA_CONTROL), 403);

        return Inertia::render('Operations/DataControl', ['processors' => DataControl::registry()]);
    }

    private function technicalAdmin(Request $request)
    {
        $user = $request->user();
        abort_unless($user !== null && Gate::forUser($user)->allows(PageAccess::OPERATIONS_TECHNICAL), 403);

        return $user;
    }
}
