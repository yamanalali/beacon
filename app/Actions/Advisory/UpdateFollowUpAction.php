<?php

namespace App\Actions\Advisory;

use App\Enums\AdvisoryRecordStatus;
use App\Models\FollowUpAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateFollowUpAction
{
    use AuthorizesAdvisoryStaff, RecordsAdvisoryAudit, ScopesResponsibleUser;

    /**
     * Only open follow-ups are editable; a completed one must be reopened first so its history is not rewritten.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, FollowUpAction $followUp, array $attributes): FollowUpAction
    {
        $followUp->loadMissing('enterprise');
        $enterprise = $followUp->enterprise;

        abort_unless($this->canManage($actor, $enterprise), 403);

        if ($followUp->status !== AdvisoryRecordStatus::Open) {
            throw ValidationException::withMessages([
                'description' => ui('validation.follow_up_completed_not_editable'),
            ]);
        }

        // Absent key = keep the current responsible person; explicit null clears it.
        $responsibleProvided = array_key_exists('responsible_user_id', $attributes);

        if ($responsibleProvided) {
            $this->assertResponsibleUserBelongs($enterprise, $attributes['responsible_user_id']);
        }

        $before = $this->snapshot($followUp);

        $followUp->description = $attributes['description'];

        if ($responsibleProvided) {
            $followUp->responsible_user_id = $attributes['responsible_user_id'];
        }

        $followUp->target_date = $attributes['target_date'] ?? null;
        $followUp->save();

        $this->recordAdvisoryAudit($actor, 'follow_up.updated', $enterprise, [
            'follow_up_action_id' => $followUp->id,
            'before' => $before,
            'after' => $this->snapshot($followUp),
        ]);

        return $followUp;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FollowUpAction $followUp): array
    {
        return [
            'description' => $followUp->description,
            'responsible_user_id' => $followUp->responsible_user_id,
            'target_date' => $followUp->target_date?->toDateString(),
        ];
    }
}
