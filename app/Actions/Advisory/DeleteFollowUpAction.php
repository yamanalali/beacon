<?php

namespace App\Actions\Advisory;

use App\Models\FollowUpAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteFollowUpAction
{
    use AuthorizesAdvisoryStaff, RecordsAdvisoryAudit;

    public function execute(User $actor, FollowUpAction $followUp): void
    {
        $followUp->loadMissing('enterprise');
        $enterprise = $followUp->enterprise;

        abort_unless($this->canManage($actor, $enterprise), 403);

        DB::transaction(function () use ($actor, $followUp, $enterprise): void {
            $this->recordAdvisoryAudit($actor, 'follow_up.deleted', $enterprise, [
                'follow_up_action_id' => $followUp->id,
                'advisory_activity_id' => $followUp->advisory_activity_id,
                'description' => $followUp->description,
                'status' => $followUp->status->value,
                'responsible_user_id' => $followUp->responsible_user_id,
                'target_date' => $followUp->target_date?->toDateString(),
            ]);

            $followUp->delete();
        });
    }
}
