<?php

namespace App\Actions\Compliance;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Documents\StoreDocumentAction;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceSubmission;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\User;
use App\Support\ReadinessAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitComplianceDocumentAction
{
    public function __construct(
        private readonly StoreDocumentAction $storeDocument,
        private readonly RecordAuditLog $audit,
    ) {}

    public function execute(
        User $actor,
        Enterprise $enterprise,
        ComplianceRequirement $requirement,
        UploadedFile $file,
        ?string $title = null,
    ): ComplianceSubmission {
        abort_unless(ReadinessAccess::isEnterpriseMember($actor, $enterprise), 403);

        $version = $requirement->currentVersion();
        abort_if(! $requirement->is_active || $version === null, 422, 'المتطلب غير فعّال أو غير منشور.');
        abort_unless($version->requires_document, 422, 'هذا المتطلب لا يحتاج رفع وثيقة.');

        return DB::transaction(function () use ($actor, $enterprise, $requirement, $version, $file, $title) {
            $record = EnterpriseComplianceRecord::query()->firstOrNew([
                'enterprise_id' => $enterprise->id,
                'compliance_requirement_id' => $requirement->id,
            ]);

            if ($record->exists && $record->compliance_requirement_version_id !== $version->id) {
                $record->due_date = null;
                $record->notes = null;
                $record->status = EnterpriseComplianceRecord::STATUS_NOT_STARTED;
            }

            if ($record->exists && ! $record->allowsDocumentUpload()) {
                throw ValidationException::withMessages([
                    'file' => 'لا يمكن رفع وثيقة في الحالة الحالية. انتظر نتيجة المراجعة أو تواصل مع المستشار.',
                ]);
            }

            if (! $record->exists) {
                $record->status = EnterpriseComplianceRecord::STATUS_NOT_STARTED;
            }

            $record->compliance_requirement_version_id = $version->id;
            $record->updated_by = $actor->id;
            $record->save();

            $document = $this->storeDocument->execute(
                $actor,
                $enterprise,
                $file,
                $title ?: $version->title_ar,
                null,
            );

            $submission = $record->submissions()->create([
                'document_id' => $document->id,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'review_status' => ComplianceSubmission::REVIEW_PENDING,
            ]);

            $record->forceFill([
                'status' => EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit->record($actor, 'compliance.document_submitted', $record, [
                'enterprise' => $enterprise->public_id,
                'requirement' => $requirement->code,
                'version_number' => $version->version_number,
                'submission_id' => $submission->id,
                'document_public_id' => $document->public_id,
            ]);

            return $submission->load('document');
        });
    }
}
