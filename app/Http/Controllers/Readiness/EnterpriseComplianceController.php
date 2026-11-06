<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Compliance\ReviewComplianceSubmissionAction;
use App\Actions\Compliance\SubmitComplianceDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compliance\ReviewComplianceSubmissionRequest;
use App\Http\Requests\Compliance\SubmitComplianceDocumentRequest;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceSubmission;
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
 * deadlines and actions needed. SME members submit documents; advisors/admins review them.
 * TODO: BUSINESS DECISION REQUIRED - approved notification channels for automated compliance reminders.
 */
class EnterpriseComplianceController extends Controller
{
    public function index(Request $request, Enterprise $enterprise): Response
    {
        abort_unless(ReadinessAccess::canViewEnterprise($request->user(), $enterprise), 403);

        $records = EnterpriseComplianceRecord::query()
            ->where('enterprise_id', $enterprise->id)
            ->with(['version', 'submissions' => fn ($q) => $q->with('document')->latest('submitted_at')->latest('id')])
            ->get()
            ->keyBy('compliance_requirement_id');
        $window = config('readiness.deadline_window_days');
        $today = now()->startOfDay();
        $canSubmit = ReadinessAccess::isEnterpriseMember($request->user(), $enterprise);
        $canReview = ReadinessAccess::canManageEnterprise($request->user(), $enterprise);

        $items = ComplianceRequirement::query()->where('is_active', true)->orderBy('code')->get()
            ->map(function (ComplianceRequirement $requirement) use ($records, $window, $today, $canSubmit, $canReview) {
                $version = $requirement->currentVersion();
                if ($version === null) {
                    return null;
                }
                $record = $records->get($requirement->id);
                $needsReview = $record !== null && $record->compliance_requirement_version_id !== $version->id;
                $status = $needsReview ? EnterpriseComplianceRecord::STATUS_NOT_STARTED : ($record?->status ?? EnterpriseComplianceRecord::STATUS_NOT_STARTED);
                $due = $needsReview ? null : $record?->due_date;
                $completed = $status === EnterpriseComplianceRecord::STATUS_COMPLETED;
                $requiresDocument = (bool) $version->requires_document;
                $latest = $needsReview ? null : $record?->submissions->first();
                $canUpload = $requiresDocument
                    && $canSubmit
                    && ! $needsReview
                    && in_array($status, EnterpriseComplianceRecord::UPLOADABLE_STATUSES, true);

                return [
                    'requirement_public_id' => $requirement->public_id,
                    'category_label' => ComplianceRequirement::CATEGORIES[$requirement->category] ?? $requirement->category,
                    'version' => ComplianceAdminController::versionData($version),
                    'requires_document' => $requiresDocument,
                    'recorded_version_number' => $record?->version?->version_number,
                    'needs_version_review' => $needsReview,
                    'recorded_status_label' => $record ? (EnterpriseComplianceRecord::STATUSES[$record->status] ?? $record->status) : null,
                    'status' => $status,
                    'status_label' => EnterpriseComplianceRecord::STATUSES[$status] ?? $status,
                    'due_date' => $due?->toDateString(),
                    'notes' => $needsReview ? null : $record?->notes,
                    'missing_document' => $requiresDocument && in_array($status, EnterpriseComplianceRecord::UPLOADABLE_STATUSES, true),
                    'overdue' => ! $completed && $due !== null && $due->lt($today),
                    'approaching' => ! $completed && $due !== null && $due->gte($today)
                        && ($window === null || $due->lte($today->copy()->addDays($window))),
                    'action_needed' => ! $completed,
                    'can_upload' => $canUpload,
                    'can_review' => $canReview && $status === EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW && $latest?->isPending(),
                    'latest_submission' => $latest === null ? null : [
                        'id' => $latest->id,
                        'original_name' => $latest->document?->original_name,
                        'title' => $latest->document?->title,
                        'submitted_at' => $latest->submitted_at?->toIso8601String(),
                        'review_status' => $latest->review_status,
                        'review_status_label' => ComplianceSubmission::REVIEW_STATUSES[$latest->review_status] ?? $latest->review_status,
                        'review_note' => $latest->review_note,
                        'document_public_id' => $latest->document?->public_id,
                        'document_url' => $latest->document
                            ? route('documents.download', $latest->document)
                            : null,
                    ],
                ];
            })->filter()->values();

        $staffStatuses = collect(EnterpriseComplianceRecord::STATUSES)
            ->except([
                EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW,
                EnterpriseComplianceRecord::STATUS_REJECTED,
                EnterpriseComplianceRecord::STATUS_NEEDS_RESUBMISSION,
            ])
            ->all();

        return Inertia::render('Compliance/Enterprise', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'items' => $items,
            'statuses' => $staffStatuses,
            'canUpdate' => $canReview,
            'canSubmit' => $canSubmit,
            'canReview' => $canReview,
        ]);
    }

    public function update(Request $request, Enterprise $enterprise, ComplianceRequirement $requirement, RecordAuditLog $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $enterprise), 403);
        $version = $requirement->currentVersion();
        abort_if(! $requirement->is_active || $version === null, 422, 'المتطلب غير فعّال أو غير منشور.');

        $record = EnterpriseComplianceRecord::query()->firstOrNew([
            'enterprise_id' => $enterprise->id, 'compliance_requirement_id' => $requirement->id,
        ]);

        $staffEditableStatuses = $version->requires_document
            ? [
                EnterpriseComplianceRecord::STATUS_NOT_STARTED,
                EnterpriseComplianceRecord::STATUS_IN_PROGRESS,
            ]
            : [
                EnterpriseComplianceRecord::STATUS_NOT_STARTED,
                EnterpriseComplianceRecord::STATUS_IN_PROGRESS,
                EnterpriseComplianceRecord::STATUS_COMPLETED,
            ];

        // Document-required items may already be in a review workflow status; staff can still edit due_date/notes.
        $rules = [
            'version_number' => ['sometimes', 'integer', Rule::in([$version->version_number])],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($version->requires_document && $record->exists && ! in_array($record->status, $staffEditableStatuses, true)) {
            $rules['status'] = ['sometimes', Rule::in([$record->status])];
        } else {
            $rules['status'] = ['required', Rule::in($staffEditableStatuses)];
        }

        $data = $request->validate($rules);

        $before = $record->exists ? $record->only(['status', 'due_date', 'notes', 'compliance_requirement_version_id']) : null;
        if ($record->exists && $record->compliance_requirement_version_id !== $version->id) {
            $record->due_date = null;
            $record->notes = null;
        }
        unset($data['version_number']);
        if (! array_key_exists('status', $data) && $record->exists) {
            // Keep workflow status when only due date / notes change.
            $data['status'] = $record->status;
        }
        $record->fill($data + ['compliance_requirement_version_id' => $version->id, 'updated_by' => $actor->id])->save();

        $audit->record($actor, 'compliance.enterprise_status_updated', $record, [
            'enterprise' => $enterprise->public_id, 'requirement' => $requirement->code,
            'version_number' => $version->version_number, 'before' => $before, 'after' => $data,
        ]);

        return redirect()->route('enterprises.compliance.index', $enterprise);
    }

    public function submit(
        SubmitComplianceDocumentRequest $request,
        Enterprise $enterprise,
        ComplianceRequirement $requirement,
        SubmitComplianceDocumentAction $action,
    ): RedirectResponse {
        $action->execute(
            $request->user(),
            $enterprise,
            $requirement,
            $request->file('file'),
            $request->validated('title'),
        );

        return redirect()->route('enterprises.compliance.index', $enterprise)
            ->with('success', 'تم رفع الوثيقة وهي بانتظار مراجعة المستشار.');
    }

    public function review(
        ReviewComplianceSubmissionRequest $request,
        Enterprise $enterprise,
        ComplianceRequirement $requirement,
        ComplianceSubmission $submission,
        ReviewComplianceSubmissionAction $action,
    ): RedirectResponse {
        abort_unless(
            (int) $submission->record?->enterprise_id === (int) $enterprise->id
            && (int) $submission->record?->compliance_requirement_id === (int) $requirement->id,
            404,
        );

        $action->execute(
            $request->user(),
            $enterprise,
            $submission,
            $request->validated('decision'),
            $request->validated('review_note'),
        );

        return redirect()->route('enterprises.compliance.index', $enterprise)
            ->with('success', 'تم حفظ قرار المراجعة.');
    }
}
