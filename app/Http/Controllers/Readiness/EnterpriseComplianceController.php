<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\ComplianceRequirement;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Support\ReadinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Section 22: SME users and authorized advisors see applicable requirements, status, missing documents,
 * deadlines and actions needed.
 * TODO: BUSINESS DECISION REQUIRED - approved notification channels for automated compliance reminders (not implemented here; section 43 owner).
 * TODO: BUSINESS DECISION REQUIRED - whether SME users may update their own compliance status (currently Beacon staff only).
 */
class EnterpriseComplianceController extends Controller
{
    public function index(Request $request, Enterprise $enterprise): Response
    {
        abort_unless(ReadinessAccess::canViewEnterprise($request->user(), $enterprise), 403);

        $records = EnterpriseComplianceRecord::query()->where('enterprise_id', $enterprise->id)->with('version')->get()
            ->keyBy('compliance_requirement_id');
        $window = config('readiness.deadline_window_days');
        $today = now()->startOfDay();

        $items = ComplianceRequirement::query()->where('is_active', true)->orderBy('code')->get()
            ->map(function (ComplianceRequirement $requirement) use ($records, $window, $today) {
                $version = $requirement->currentVersion();
                if ($version === null) {
                    return null;
                }
                $record = $records->get($requirement->id);
                $status = $record?->status ?? 'not_started';
                $due = $record?->due_date;
                $completed = $status === 'completed';

                return [
                    'requirement_public_id' => $requirement->public_id,
                    'category_label' => ComplianceRequirement::CATEGORIES[$requirement->category] ?? $requirement->category,
                    'version' => ComplianceAdminController::versionData($version),
                    'recorded_version_number' => $record?->version?->version_number,
                    'status' => $status,
                    'status_label' => EnterpriseComplianceRecord::STATUSES[$status] ?? $status,
                    'due_date' => $due?->toDateString(),
                    'notes' => $record?->notes,
                    'missing_document' => $version->requires_document && ! $completed,
                    'overdue' => ! $completed && $due !== null && $due->lt($today),
                    'approaching' => ! $completed && $due !== null && $due->gte($today)
                        && ($window === null || $due->lte($today->copy()->addDays($window))),
                    'action_needed' => ! $completed,
                ];
            })->filter()->values();

        return Inertia::render('Compliance/Enterprise', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'items' => $items,
            'statuses' => EnterpriseComplianceRecord::STATUSES,
            'canUpdate' => ReadinessAccess::canManageEnterprise($request->user(), $enterprise),
        ]);
    }

    public function update(Request $request, Enterprise $enterprise, ComplianceRequirement $requirement, RecordAuditLog $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $enterprise), 403);
        $version = $requirement->currentVersion();
        abort_if(! $requirement->is_active || $version === null, 422, 'المتطلب غير فعّال أو غير منشور.');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(EnterpriseComplianceRecord::STATUSES))],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = EnterpriseComplianceRecord::query()->firstOrNew([
            'enterprise_id' => $enterprise->id, 'compliance_requirement_id' => $requirement->id,
        ]);
        $before = $record->exists ? $record->only(['status', 'due_date', 'notes', 'compliance_requirement_version_id']) : null;
        $record->fill($data + ['compliance_requirement_version_id' => $version->id, 'updated_by' => $actor->id])->save();

        $audit->record($actor, 'compliance.enterprise_status_updated', $record, [
            'enterprise' => $enterprise->public_id, 'requirement' => $requirement->code,
            'version_number' => $version->version_number, 'before' => $before, 'after' => $data,
        ]);

        return redirect()->route('enterprises.compliance.index', $enterprise);
    }
}
