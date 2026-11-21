<?php

namespace App\Ai\Actions;

use App\Actions\Audit\RecordAuditLog;
use App\Ai\Models\AiEscalation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReopenAiEscalationAction
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * answered → open. The reply fields are cleared on the row; the previous reply is kept in the audit entry.
     */
    public function execute(User $actor, AiEscalation $escalation): AiEscalation
    {
        DB::transaction(function () use ($actor, $escalation): void {
            $previous = [
                'previous_staff_reply' => $escalation->staff_reply,
                'previous_replied_by' => $escalation->replied_by,
                'previous_replied_at' => $escalation->replied_at?->toIso8601String(),
            ];

            $escalation->forceFill([
                'status' => AiEscalation::STATUS_OPEN,
                'staff_reply' => null,
                'replied_by' => null,
                'replied_at' => null,
            ])->save();

            $this->audit->record($actor, 'ai.escalation.reopened', $escalation, [
                'status' => AiEscalation::STATUS_OPEN,
            ] + $previous);
        });

        return $escalation;
    }
}
