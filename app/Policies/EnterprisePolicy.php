<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;

class EnterprisePolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->permission('view', true)) {
            return false;
        }

        return $this->isActive($user, UserRole::BeaconAdmin)
            || $this->isActive($user, UserRole::BeaconAdvisor);
    }

    public function view(User $user, Enterprise $enterprise): bool
    {
        if (! $user->permission('view', true)) {
            return false;
        }

        if ($user->role === UserRole::MfiUser || $user->role === UserRole::TechnicalAdmin) {
            return false;
        }

        if ($this->isActive($user, UserRole::BeaconAdmin)) {
            return true;
        }

        if ($this->isActive($user, UserRole::BeaconAdvisor)) {
            return $enterprise->advisors()->whereKey($user->id)->exists();
        }

        if ($this->isActive($user, UserRole::SmeUser)) {
            return $enterprise->members()->whereKey($user->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        if (! $user->permission('view', true)) {
            return false;
        }

        return $this->isActive($user, UserRole::BeaconAdmin)
            || $this->isActive($user, UserRole::BeaconAdvisor);
    }

    public function update(User $user, Enterprise $enterprise): bool
    {
        return $this->view($user, $enterprise);
    }

    public function assignMembers(User $user, Enterprise $enterprise): bool
    {
        return $this->isActive($user, UserRole::BeaconAdmin);
    }

    private function isActive(User $user, UserRole $role): bool
    {
        return $user->isActive() && $user->role === $role;
    }
}
