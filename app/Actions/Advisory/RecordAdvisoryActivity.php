<?php

namespace App\Actions\Advisory;

use App\Enums\AdvisoryRecordStatus;
use App\Enums\UserRole;
use App\Models\AdvisoryActivity;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordAdvisoryActivity
{
    use RecordsAdvisoryAudit;

    public function __construct(private CreateFollowUpAction $createFollowUpAction) {}

    /**
     * TODO: BUSINESS DECISION REQUIRED
     * Mandatory advisory fields are not confirmed. Only activity_date and the enterprise are required.
     * Notes, responsible person, target date, and an optional follow-up stay optional until Beacon confirms the form.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, Enterprise $enterprise, array $attributes): AdvisoryActivity
    {
        abort_unless($this->canRecord($actor, $enterprise), 403);

        $followUpDescription = $this->blankToNull($attributes['follow_up_description'] ?? null);

        $write = function () use ($actor, $enterprise, $attributes, $followUpDescription): AdvisoryActivity {
            $activity = new AdvisoryActivity;
            $activity->enterprise_id = $enterprise->id;
            $activity->activity_date = $attributes['activity_date'];
            $activity->notes = $this->blankToNull($attributes['notes'] ?? null);
            $activity->responsible_user_id = $attributes['responsible_user_id'] ?? null;
            $activity->target_date = $attributes['target_date'] ?? null;
            $activity->status = AdvisoryRecordStatus::Open;
            $activity->created_by = $actor->id;
            $activity->save();

            if ($followUpDescription !== null) {
                $this->createFollowUpAction->execute($actor, $enterprise, [
                    'description' => $followUpDescription,
                    'advisory_activity_id' => $activity->id,
                    'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
                    'target_date' => $attributes['target_date'] ?? null,
                ]);
            }

            $this->recordAdvisoryAudit($actor, 'advisory.recorded', $enterprise, [
                'advisory_activity_id' => $activity->id,
                'activity_date' => $activity->activity_date?->toDateString(),
                'status' => $activity->status->value,
            ]);

            return $activity;
        };

        if ($followUpDescription !== null) {
            return DB::transaction($write);
        }

        return $write();
    }

    private function canRecord(User $actor, Enterprise $enterprise): bool
    {
        return $actor->isActive()
            && in_array($actor->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true)
            && $actor->can('update', $enterprise);
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
