<?php

namespace App\Actions\Advisory;

use App\Enums\AdvisoryRecordStatus;
use App\Enums\UserRole;
use App\Models\AdvisoryActivity;
use App\Models\Enterprise;
use App\Models\FollowUpAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFollowUpAction
{
    use ScopesResponsibleUser, RecordsAdvisoryAudit;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, Enterprise $enterprise, array $attributes): FollowUpAction
    {
        abort_unless($this->canRecord($actor, $enterprise), 403);

        $this->assertResponsibleUserBelongs($enterprise, $attributes['responsible_user_id'] ?? null);

        $activityId = $attributes['advisory_activity_id'] ?? null;

        if ($activityId !== null) {
            $belongsToEnterprise = AdvisoryActivity::query()
                ->whereKey($activityId)
                ->where('enterprise_id', $enterprise->id)
                ->exists();

            if (! $belongsToEnterprise) {
                throw ValidationException::withMessages([
                    'advisory_activity_id' => 'النشاط الإرشادي لا يتبع هذه المنشأة.',
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $enterprise, $attributes, $activityId): FollowUpAction {
            $followUp = new FollowUpAction;
            $followUp->enterprise_id = $enterprise->id;
            $followUp->advisory_activity_id = $activityId;
            $followUp->description = $attributes['description'];
            $followUp->responsible_user_id = $attributes['responsible_user_id'] ?? null;
            $followUp->target_date = $attributes['target_date'] ?? null;
            $followUp->status = AdvisoryRecordStatus::Open;
            $followUp->created_by = $actor->id;
            $followUp->save();

            $this->recordAdvisoryAudit($actor, 'follow_up.created', $enterprise, [
                'follow_up_action_id' => $followUp->id,
                'advisory_activity_id' => $followUp->advisory_activity_id,
                'status' => $followUp->status->value,
            ]);

            return $followUp;
        });
    }

    private function canRecord(User $actor, Enterprise $enterprise): bool
    {
        return $actor->isActive()
            && in_array($actor->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true)
            && $actor->can('update', $enterprise);
    }
}
