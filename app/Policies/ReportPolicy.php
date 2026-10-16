<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;

/**
 * Financial reports and their exports follow enterprise access exactly:
 * SME members, assigned advisors and Beacon admins. MFI users and
 * technical admins are denied (EnterprisePolicy::view).
 */
class ReportPolicy
{
    public function view(User $user, Enterprise $enterprise): bool
    {
        return $user->isActive()
            && in_array($user->role, [UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::BeaconAdmin], true)
            && $user->can('view', $enterprise);
    }

    public function export(User $user, Enterprise $enterprise): bool
    {
        return $this->view($user, $enterprise);
    }
}
