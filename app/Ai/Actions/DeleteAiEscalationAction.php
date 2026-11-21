<?php

namespace App\Ai\Actions;

use App\Actions\Audit\RecordAuditLog;
use App\Ai\Models\AiEscalation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAiEscalationAction
{
    public function __construct(private RecordAuditLog $audit) {}

    public function execute(User $actor, AiEscalation $escalation): void
    {
        DB::transaction(function () use ($actor, $escalation): void {
            // The audit entry keeps what was removed (question text and status).
            $this->audit->record($actor, 'ai.escalation.deleted', $escalation, [
                'status' => $escalation->status,
                'question' => $escalation->question,
                'asked_by' => $escalation->user_id,
                'enterprise_id' => $escalation->enterprise_id,
                'had_reply' => $escalation->staff_reply !== null,
            ]);

            $escalation->delete();
        });
    }
}
