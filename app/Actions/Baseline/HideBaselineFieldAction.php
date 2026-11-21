<?php

namespace App\Actions\Baseline;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\BaselineProfileField;
use App\Models\User;

/** Logical delete: the field and its stored answers are kept; the profile page just stops showing it. */
class HideBaselineFieldAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, BaselineProfileField $field): void
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if (! $field->is_visible) {
            return;
        }

        $field->forceFill(['is_visible' => false])->save();

        $this->audit->record($actor, 'baseline.field.hidden', $field, ['key' => $field->key]);
    }
}
