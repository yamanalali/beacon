<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Only an active Beacon administrator may change another user's role.
     */
    public function updateRole(User $user, User $target): bool
    {
        return $user->isActive()
            && $user->role === UserRole::BeaconAdmin
            && ! $user->is($target);
    }

    /**
     * Only an active Beacon administrator may correct another user's name or email.
     * Own details are changed from the account screen, not from user administration.
     */
    public function updateProfile(User $user, User $target): bool
    {
        return $user->isActive()
            && $user->role === UserRole::BeaconAdmin
            && ! $user->is($target);
    }

    /**
     * Only an active Beacon administrator may deactivate another user.
     */
    public function deactivate(User $user, User $target): bool
    {
        return $user->isActive()
            && $user->role === UserRole::BeaconAdmin
            && ! $user->is($target);
    }
}
