<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/** Transaction categories are programme reference data managed by Beacon administrators only. */
class TransactionCategoryPolicy
{
    public function manage(User $user): bool
    {
        return $user->isActive() && $user->role === UserRole::BeaconAdmin;
    }
}
