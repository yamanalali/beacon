<?php

namespace App\Ai\Policies;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\User;

/**
 * TODO: BUSINESS DECISION REQUIRED — escalation recipients (rules §35).
 * Interim: the asker sees own escalations; BEACON_ADMIN sees all; an advisor
 * sees escalations for enterprises assigned to them. MFI/technical admin: none.
 */
class AiEscalationPolicy
{
    public function view(User $user, AiEscalation $e): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return match ($user->role) {
            UserRole::BeaconAdmin => true,
            UserRole::BeaconAdvisor => $e->user_id === $user->id
                || ($e->enterprise_id !== null && $e->enterprise?->advisors()->whereKey($user->id)->exists()),
            UserRole::SmeUser => $e->user_id === $user->id,
            default => false,
        };
    }
}
