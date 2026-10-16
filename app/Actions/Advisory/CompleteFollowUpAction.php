<?php

namespace App\Actions\Advisory;

use App\Enums\AdvisoryRecordStatus;
use App\Enums\UserRole;
use App\Models\FollowUpAction;
use App\Models\User;

class CompleteFollowUpAction
{
    use RecordsAdvisoryAudit;

    public function execute(User $actor, FollowUpAction $followUp): FollowUpAction
    {
        abort_unless($this->canComplete($actor, $followUp), 403);

        $followUp->status = AdvisoryRecordStatus::Completed;
        $followUp->save();

        $followUp->loadMissing('enterprise');

        $this->recordAdvisoryAudit($actor, 'follow_up.completed', $followUp->enterprise, [
            'follow_up_action_id' => $followUp->id,
            'status' => $followUp->status->value,
        ]);

        return $followUp;
    }

    public function canComplete(User $actor, FollowUpAction $followUp): bool
    {
        if (! $actor->isActive()) {
            return false;
        }

        if ($actor->role === UserRole::MfiUser || $actor->role === UserRole::TechnicalAdmin) {
            return false;
        }

        $enterprise = $followUp->enterprise;

        if ($actor->role === UserRole::BeaconAdmin && $actor->can('view', $enterprise)) {
            return true;
        }

        if ($actor->role === UserRole::BeaconAdvisor && $actor->can('update', $enterprise)) {
            return true;
        }

        return $followUp->responsible_user_id !== null
            && (int) $followUp->responsible_user_id === (int) $actor->id;
    }
}
