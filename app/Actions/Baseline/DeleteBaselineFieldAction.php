<?php

namespace App\Actions\Baseline;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\BaselineProfileField;
use App\Models\EnterpriseBaselineProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBaselineFieldAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** Only a non-system field that no enterprise has an answer for may be removed; otherwise hide it. */
    public function execute(User $actor, BaselineProfileField $field): void
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if ($field->is_system) {
            throw ValidationException::withMessages(['field' => ui('validation.baseline_field_system')]);
        }

        DB::transaction(function () use ($actor, $field): void {
            if ($this->isUsed($field)) {
                throw ValidationException::withMessages(['field' => ui('validation.baseline_field_in_use')]);
            }

            $this->audit->record($actor, 'baseline.field.deleted', $field, [
                'key' => $field->key,
                'label_ar' => $field->label_ar,
                'excel_column' => $field->excel_column,
            ]);

            $field->delete();
        });
    }

    private function isUsed(BaselineProfileField $field): bool
    {
        // Keys are restricted to [a-z0-9_] on creation; catalog keys are the same shape, but be strict anyway.
        if (! preg_match('/^[A-Za-z0-9_]+$/', $field->key)) {
            return true;
        }

        return EnterpriseBaselineProfile::query()
            ->whereNotNull('answers->'.$field->key)
            ->exists();
    }
}
