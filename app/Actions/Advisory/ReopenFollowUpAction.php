<?php

namespace App\Actions\Advisory;

use App\Enums\AdvisoryRecordStatus;
use App\Models\FollowUpAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReopenFollowUpAction
{
    use AuthorizesAdvisoryStaff, RecordsAdvisoryAudit;

    /**
     * completed → open. The follow-up has no completion timestamp column, so status is the only state to reset.
     */
    public function execute(User $actor, FollowUpAction $followUp): FollowUpAction
    {
        $followUp->loadMissing('enterprise');
        $enterprise = $followUp->enterprise;

        abort_unless($this->canManage($actor, $enterprise), 403);

        if ($followUp->status !== AdvisoryRecordStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => ui('validation.follow_up_not_completed'),
            ]);
        }

        $followUp->status = AdvisoryRecordStatus::Open;
        $followUp->save();

        $this->recordAdvisoryAudit($actor, 'follow_up.reopened', $enterprise, [
            'follow_up_action_id' => $followUp->id,
            'status' => $followUp->status->value,
        ]);

        return $followUp;
    }
}
