<?php

namespace App\Actions\Advisory;

use App\Models\AdvisoryActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAdvisoryActivityAction
{
    use AuthorizesAdvisoryStaff, RecordsAdvisoryAudit;

    /**
     * Linked follow-ups are kept (their activity link is nulled by the foreign key).
     * The audit entry keeps what was removed.
     */
    public function execute(User $actor, AdvisoryActivity $activity): void
    {
        $activity->loadMissing('enterprise');
        $enterprise = $activity->enterprise;

        abort_unless($this->canManage($actor, $enterprise), 403);

        DB::transaction(function () use ($actor, $activity, $enterprise): void {
            $this->recordAdvisoryAudit($actor, 'advisory.deleted', $enterprise, [
                'advisory_activity_id' => $activity->id,
                'activity_date' => $activity->activity_date?->toDateString(),
                'status' => $activity->status->value,
                'responsible_user_id' => $activity->responsible_user_id,
                'target_date' => $activity->target_date?->toDateString(),
                'notes' => $activity->notes,
            ]);

            $activity->delete();
        });
    }
}
