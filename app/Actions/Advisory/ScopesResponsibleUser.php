<?php

namespace App\Actions\Advisory;

use App\Models\Enterprise;
use Illuminate\Validation\ValidationException;

/**
 * Tenant isolation: a responsible person must belong to THIS enterprise (member or assigned advisor),
 * same rule as readiness result evidence. Stops assigning users of another enterprise / MFI.
 *
 * TODO: BUSINESS DECISION REQUIRED - whether unassigned Beacon admins may also be named responsible.
 */
trait ScopesResponsibleUser
{
    protected function assertResponsibleUserBelongs(Enterprise $enterprise, mixed $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $allowed = $enterprise->members()->whereKey($userId)->exists()
            || $enterprise->advisors()->whereKey($userId)->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'responsible_user_id' => 'المسؤول يجب أن يكون من أعضاء المنشأة أو مستشاريها.',
            ]);
        }
    }
}
