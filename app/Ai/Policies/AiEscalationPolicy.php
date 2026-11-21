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

    /** Assigned advisor or Beacon admin may send an answered escalation back to open. */
    public function reopen(User $user, AiEscalation $e): bool
    {
        if (! $user->isActive() || $e->isOpen()) {
            return false;
        }

        return match ($user->role) {
            UserRole::BeaconAdmin => true,
            UserRole::BeaconAdvisor => $e->enterprise_id !== null
                && $e->enterprise?->advisors()->whereKey($user->id)->exists(),
            default => false,
        };
    }

    /**
     * Beacon admin may delete any escalation. The asker may withdraw their own question
     * only while it is still open (nobody has answered it yet).
     */
    public function delete(User $user, AiEscalation $e): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return match ($user->role) {
            UserRole::BeaconAdmin => true,
            UserRole::BeaconAdvisor, UserRole::SmeUser => $e->isOpen() && $e->user_id === $user->id,
            default => false,
        };
    }

    /** Assigned advisor or Beacon admin may reply while the escalation is still open. */
    public function reply(User $user, AiEscalation $e): bool
    {
        if (! $user->isActive() || ! $e->isOpen()) {
            return false;
        }

        return match ($user->role) {
            UserRole::BeaconAdmin => true,
            UserRole::BeaconAdvisor => $e->enterprise_id !== null
                && $e->enterprise?->advisors()->whereKey($user->id)->exists(),
            default => false,
        };
    }
}
