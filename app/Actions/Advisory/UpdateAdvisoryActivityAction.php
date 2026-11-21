<?php

namespace App\Actions\Advisory;

use App\Models\AdvisoryActivity;
use App\Models\User;

class UpdateAdvisoryActivityAction
{
    use AuthorizesAdvisoryStaff, RecordsAdvisoryAudit, ScopesResponsibleUser;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, AdvisoryActivity $activity, array $attributes): AdvisoryActivity
    {
        $activity->loadMissing('enterprise');
        $enterprise = $activity->enterprise;

        abort_unless($this->canManage($actor, $enterprise), 403);

        // Absent key = keep the current responsible person; explicit null clears it.
        $responsibleProvided = array_key_exists('responsible_user_id', $attributes);

        if ($responsibleProvided) {
            $this->assertResponsibleUserBelongs($enterprise, $attributes['responsible_user_id']);
        }

        $before = $this->snapshot($activity);

        $activity->activity_date = $attributes['activity_date'];
        $activity->notes = $this->blankToNull($attributes['notes'] ?? null);

        if ($responsibleProvided) {
            $activity->responsible_user_id = $attributes['responsible_user_id'];
        }

        $activity->target_date = $attributes['target_date'] ?? null;
        $activity->save();

        $this->recordAdvisoryAudit($actor, 'advisory.updated', $enterprise, [
            'advisory_activity_id' => $activity->id,
            'before' => $before,
            'after' => $this->snapshot($activity),
        ]);

        return $activity;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AdvisoryActivity $activity): array
    {
        return [
            'activity_date' => $activity->activity_date?->toDateString(),
            'responsible_user_id' => $activity->responsible_user_id,
            'target_date' => $activity->target_date?->toDateString(),
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
