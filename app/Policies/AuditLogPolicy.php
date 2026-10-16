<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Audit history is read-only for everyone. Only active Beacon admins may view it.
 *
 * TODO: BUSINESS DECISION REQUIRED
 * Confirm whether technical admins may see a restricted (security-only) audit
 * view. Audit context contains programme/business data, so they are denied
 * until Beacon decides.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->role === UserRole::BeaconAdmin;
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
