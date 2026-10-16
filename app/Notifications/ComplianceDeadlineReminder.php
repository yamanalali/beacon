<?php

namespace App\Notifications;

use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder of an approaching compliance deadline (rules §43).
 * Payload carries only what the recipient may already see: requirement
 * title, due date and enterprise public id. No numeric ids or notes.
 */
class ComplianceDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $enterprisePublicId,
        public readonly string $enterpriseName,
        public readonly string $requirementTitle,
        public readonly string $dueDate,
    ) {}

    public static function fromRecord(EnterpriseComplianceRecord $record, Enterprise $enterprise): self
    {
        return new self(
            $enterprise->public_id,
            $enterprise->business_name,
            (string) $record->version?->title_ar,
            $record->due_date->toDateString(),
        );
    }

    /** TODO: BUSINESS DECISION REQUIRED — other channels; database only for now. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'compliance_deadline',
            'title' => 'تذكير بموعد امتثال قريب',
            'message' => 'يقترب موعد استكمال متطلب «'.$this->requirementTitle.'» لمنشأة '.$this->enterpriseName.' بتاريخ '.$this->dueDate.'.',
            'enterprise_public_id' => $this->enterprisePublicId,
            'due_date' => $this->dueDate,
        ];
    }
}
