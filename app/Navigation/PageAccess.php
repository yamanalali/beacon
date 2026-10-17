<?php

namespace App\Navigation;

use App\Enums\UserRole;
use App\Models\Enterprise;
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

    public const TRANSACTIONS_ENTRY = 'page.transactions.entry';

    public const DOCUMENTS_ENTRY = 'page.documents.entry';

    public const REPORTS_ENTRY = 'page.reports.entry';

    public static function register(): void
    {
        Gate::define(self::DASHBOARD_SME, fn (User $user): bool => self::is($user, UserRole::SmeUser));

        // TODO: BUSINESS DECISION REQUIRED - which Beacon roles see the programme dashboard (see routes/frontend.php).
        Gate::define(self::DASHBOARD_BEACON, fn (User $user): bool => self::is($user, UserRole::BeaconAdmin, UserRole::BeaconAdvisor) && $user->permission('view', true));

        // TODO: BUSINESS DECISION REQUIRED - whether BEACON_ADMIN should also see backup status (see OperationsController).
        Gate::define(self::OPERATIONS_TECHNICAL, fn (User $user): bool => self::is($user, UserRole::TechnicalAdmin));

        Gate::define(self::OPERATIONS_DATA_CONTROL, fn (User $user): bool => self::is($user, UserRole::TechnicalAdmin, UserRole::BeaconAdmin));

        self::registerSectionEntries();

        Gate::define(self::MFI_REFERRAL_INBOX, fn (User $user): bool => self::is($user, UserRole::MfiUser) && $user->mfi_institution_id !== null);
    }

    /**
     * The /transactions, /documents and /reports entry pages open the user's OWN enterprise section.
     * Only an active SME member has one; Beacon staff reach these sections from the enterprise page
     * (they used to be bounced to /enterprises, which looked like "no access" in the sidebar).
     */
    private static function registerSectionEntries(): void
    {
        // TODO: BUSINESS DECISION REQUIRED - whether Beacon admins/advisors get a cross-enterprise
        // transactions / documents / reports list. Until decided they open them per enterprise.
        foreach ([self::TRANSACTIONS_ENTRY, self::DOCUMENTS_ENTRY, self::REPORTS_ENTRY] as $gate) {
            Gate::define($gate, fn (User $user): bool => self::ownEnterprise($user) !== null);
        }
    }

    /** The active SME user's own enterprise (same lookup the entry controllers used). */
    public static function ownEnterprise(User $user): ?Enterprise
    {
        if (! self::is($user, UserRole::SmeUser) || ! $user->permission('view', true)) {
            return null;
        }

        return Enterprise::query()->whereHas('members', fn ($q) => $q->whereKey($user->id))->first();
    }

    private static function is(User $user, UserRole ...$roles): bool
    {
        return $user->isActive() && in_array($user->role, $roles, true);
    }
}
