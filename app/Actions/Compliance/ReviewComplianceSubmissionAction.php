<?php

namespace App\Actions\Compliance;

use App\Actions\Audit\RecordAuditLog;
use App\Models\ComplianceSubmission;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\User;
use App\Support\ReadinessAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewComplianceSubmissionAction
{
    public const DECISION_APPROVE = 'approve';

    public const DECISION_REJECT = 'reject';

    public const DECISION_REQUEST_RESUBMISSION = 'request_resubmission';

    public const DECISIONS = [
        self::DECISION_APPROVE,
        self::DECISION_REJECT,
        self::DECISION_REQUEST_RESUBMISSION,
    ];

    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(
        User $actor,
        Enterprise $enterprise,
        ComplianceSubmission $submission,
        string $decision,
        ?string $note = null,
    ): ComplianceSubmission {
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $enterprise), 403);

        $record = $submission->record;
        abort_unless($record !== null && (int) $record->enterprise_id === (int) $enterprise->id, 404);

        if (! $submission->isPending()) {
            throw ValidationException::withMessages([
                'decision' => 'تمت مراجعة هذه الوثيقة مسبقاً.',
            ]);
        }

        if ($record->status !== EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW) {
            throw ValidationException::withMessages([
                'decision' => 'المتطلب ليس بانتظار المراجعة.',
            ]);
        }

        [$reviewStatus, $recordStatus] = match ($decision) {
            self::DECISION_APPROVE => [ComplianceSubmission::REVIEW_APPROVED, EnterpriseComplianceRecord::STATUS_COMPLETED],
            self::DECISION_REJECT => [ComplianceSubmission::REVIEW_REJECTED, EnterpriseComplianceRecord::STATUS_REJECTED],
            self::DECISION_REQUEST_RESUBMISSION => [ComplianceSubmission::REVIEW_RESUBMISSION_REQUESTED, EnterpriseComplianceRecord::STATUS_NEEDS_RESUBMISSION],
            default => throw ValidationException::withMessages(['decision' => 'قرار غير صالح.']),
        };

        return DB::transaction(function () use ($actor, $enterprise, $submission, $record, $decision, $note, $reviewStatus, $recordStatus) {
            $before = [
                'record_status' => $record->status,
                'review_status' => $submission->review_status,
            ];

            $submission->forceFill([
                'review_status' => $reviewStatus,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $record->forceFill([
                'status' => $recordStatus,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit->record($actor, 'compliance.submission_reviewed', $record, [
                'enterprise' => $enterprise->public_id,
                'requirement' => $record->requirement?->code,
                'submission_id' => $submission->id,
                'decision' => $decision,
                'before' => $before,
                'after' => [
                    'record_status' => $recordStatus,
                    'review_status' => $reviewStatus,
                    'review_note' => $note,
                ],
            ]);

            return $submission->fresh(['document', 'record']);
        });
    }
}
