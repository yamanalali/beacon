<?php

namespace App\Actions\Advisory;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;

/**
 * Same rule as creating advisory records: an active Beacon admin, or an advisor assigned to the
 * enterprise, on an enterprise that is not archived. Whoever can create may also mutate.
 */
trait AuthorizesAdvisoryStaff
{
    public function canManage(User $actor, ?Enterprise $enterprise): bool
    {
        return $enterprise !== null
            && $actor->isActive()
            && in_array($actor->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true)
            && $actor->can('update', $enterprise);
    }
}
