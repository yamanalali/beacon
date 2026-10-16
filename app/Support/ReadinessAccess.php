<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\Referral;
use App\Models\User;

/**
 * Central authorization rules for sections 20-31. Every controller action calls one of these,
 * so the same checks apply to pages, direct URLs and data endpoints (section 4).
 */
class ReadinessAccess
{
    public static function isAdmin(?User $user): bool
    {
        return self::hasRole($user, UserRole::BeaconAdmin);
    }

    /** SME member, assigned advisor, or Beacon admin (reuses EnterprisePolicy@view). */
    public static function canViewEnterprise(?User $user, Enterprise $enterprise): bool
    {
        return $user !== null && $user->isActive() && $user->can('view', $enterprise);
    }

    /** Beacon staff with access to this enterprise: assigned advisor or Beacon admin. */
    public static function canManageEnterprise(?User $user, Enterprise $enterprise): bool
    {
        return (self::hasRole($user, UserRole::BeaconAdmin) || self::hasRole($user, UserRole::BeaconAdvisor))
            && $user->can('view', $enterprise);
    }

    public static function isEnterpriseMember(?User $user, Enterprise $enterprise): bool
    {
        return self::hasRole($user, UserRole::SmeUser)
            && $enterprise->members()->whereKey($user->id)->exists();
    }

    /**
     * MFI users only see referrals shared with their own institution, after sharing,
     * and only while the SME consent is still active.
     * TODO: BUSINESS DECISION REQUIRED - approved withdrawal policy; current behaviour blocks all further platform access after withdrawal.
     */
    public static function mfiCanViewReferral(?User $user, Referral $referral): bool
    {
        return self::hasRole($user, UserRole::MfiUser)
            && $user->mfi_institution_id !== null
            && (int) $user->mfi_institution_id === (int) $referral->mfi_institution_id
            && $referral->status !== Referral::STATUS_DRAFT
            && $referral->hasValidConsent();
    }

    public static function canViewReferral(?User $user, Referral $referral): bool
    {
        if ($user !== null && $user->role === UserRole::MfiUser) {
            return self::mfiCanViewReferral($user, $referral);
        }

        return self::canViewEnterprise($user, $referral->enterprise);
    }

    private static function hasRole(?User $user, UserRole $role): bool
    {
        return $user !== null && $user->isActive() && $user->role === $role;
    }
}
