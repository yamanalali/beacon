<?php

namespace App\Navigation;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Page-level abilities for pages that are not protected by a model policy or role middleware.
 * The controllers/routes call these gates, and NavigationBuilder reads the same gates,
 * so the nav can never show a page the user cannot open (or hide one they can).
 * The role lists below are copied unchanged from the checks that existed in the controllers.
 */
class PageAccess
{
    public const DASHBOARD_SME = 'page.dashboard.sme';
    public const DASHBOARD_BEACON = 'page.dashboard.beacon';
    public const OPERATIONS_TECHNICAL = 'page.operations.technical';
    public const OPERATIONS_DATA_CONTROL = 'page.operations.data-control';
    public const MFI_REFERRAL_INBOX = 'page.mfi.referral-inbox';

    public static function register(): void
    {
        Gate::define(self::DASHBOARD_SME, fn (User $user): bool => self::is($user, UserRole::SmeUser));

        // TODO: BUSINESS DECISION REQUIRED - which Beacon roles see the programme dashboard (see routes/frontend.php).
        Gate::define(self::DASHBOARD_BEACON, fn (User $user): bool => self::is($user, UserRole::BeaconAdmin, UserRole::BeaconAdvisor));

        // TODO: BUSINESS DECISION REQUIRED - whether BEACON_ADMIN should also see backup status (see OperationsController).
        Gate::define(self::OPERATIONS_TECHNICAL, fn (User $user): bool => self::is($user, UserRole::TechnicalAdmin));

        Gate::define(self::OPERATIONS_DATA_CONTROL, fn (User $user): bool => self::is($user, UserRole::TechnicalAdmin, UserRole::BeaconAdmin));

        Gate::define(self::MFI_REFERRAL_INBOX, fn (User $user): bool => self::is($user, UserRole::MfiUser) && $user->mfi_institution_id !== null);
    }

    private static function is(User $user, UserRole ...$roles): bool
    {
        return $user->isActive() && in_array($user->role, $roles, true);
    }
}
