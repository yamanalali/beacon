<?php

namespace App\Navigation;

use App\Http\Middleware\EnsureAdminMfaAvailable;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\User;
use App\Enums\UserRole;
use App\Support\ReadinessAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Builds the `auth.navigation` Inertia prop: only the pages the current user can actually open.
 *
 * Visibility = (1) the route's own middleware (role:..., EnsureAdminMfaAvailable) evaluated with the
 * same code the middleware runs, AND (2) the ability the controller checks (policy / Gate / ReadinessAccess).
 * No role list lives here.
 */
class NavigationBuilder
{
    /** @var array<int, Enterprise|null> */
    private array $ownEnterprise = [];

    /**
     * Single declarative registry.
     *
     * @return list<array{key: string, route: string, label: string, icon: string, group: string, ability: Closure(User): bool|null, params?: Closure(User): ?array, active?: list<string>}>
     */
    public function definitions(): array
    {
        $entry = fn (User $u): bool => $this->smeEnterprise($u) !== null || $u->can('viewAny', Enterprise::class);
        // Section entry pages (/transactions, /documents, /reports): same gate the entry controllers authorize.
        $section = fn (string $gate): Closure => fn (User $u): bool => Gate::forUser($u)->allows($gate);
        $smeEnterpriseParams = fn (User $u): ?array => ($e = $this->smeEnterprise($u)) ? ['enterprise' => $e] : null;
        $smeCanView = fn (User $u): bool => ($e = $this->smeEnterprise($u)) !== null && ReadinessAccess::canViewEnterprise($u, $e);

        return [
            ['key' => 'dashboard.sme', 'route' => 'dashboard.sme', 'label' => 'لوحة التحكم', 'icon' => 'layout-dashboard', 'group' => 'main', 'ability' => fn (User $u) => Gate::forUser($u)->allows(PageAccess::DASHBOARD_SME)],
            ['key' => 'dashboard.beacon', 'route' => 'dashboard.beacon', 'label' => 'لوحة البرنامج', 'icon' => 'layout-dashboard', 'group' => 'main', 'ability' => fn (User $u) => Gate::forUser($u)->allows(PageAccess::DASHBOARD_BEACON)],

            ['key' => 'enterprises', 'route' => 'enterprises.index', 'label' => 'المنشآت', 'icon' => 'building', 'group' => 'enterprise', 'ability' => $entry, 'active' => ['enterprises.index', 'enterprises.show', 'enterprises.create', 'enterprises.edit']],
            ['key' => 'transactions', 'route' => 'transactions.entry', 'label' => 'المعاملات', 'icon' => 'receipt', 'group' => 'enterprise', 'ability' => $section(PageAccess::TRANSACTIONS_ENTRY), 'active' => ['transactions.*', 'enterprises.transactions.*']],
            ['key' => 'documents', 'route' => 'documents.entry', 'label' => 'المستندات', 'icon' => 'file-text', 'group' => 'enterprise', 'ability' => $section(PageAccess::DOCUMENTS_ENTRY), 'active' => ['documents.*', 'enterprises.documents.*']],
            ['key' => 'reports', 'route' => 'reports.entry', 'label' => 'التقارير المالية', 'icon' => 'bar-chart', 'group' => 'enterprise', 'ability' => $section(PageAccess::REPORTS_ENTRY), 'active' => ['reports.*', 'enterprises.reports.*']],
            ['key' => 'readiness', 'route' => 'enterprises.readiness.index', 'label' => 'الجاهزية', 'icon' => 'gauge', 'group' => 'enterprise', 'ability' => $smeCanView, 'params' => $smeEnterpriseParams, 'active' => ['enterprises.readiness.*', 'readiness-assessments.*']],
            ['key' => 'compliance', 'route' => 'enterprises.compliance.index', 'label' => 'الامتثال', 'icon' => 'shield-check', 'group' => 'enterprise', 'ability' => $smeCanView, 'params' => $smeEnterpriseParams, 'active' => ['enterprises.compliance.*']],
            ['key' => 'referrals', 'route' => 'enterprises.referrals.index', 'label' => 'الإحالات', 'icon' => 'send', 'group' => 'enterprise', 'ability' => $smeCanView, 'params' => $smeEnterpriseParams, 'active' => ['enterprises.referrals.*', 'referrals.*']],

            ['key' => 'mfi.referrals', 'route' => 'mfi.referrals.index', 'label' => 'الإحالات الواردة', 'icon' => 'inbox', 'group' => 'mfi', 'ability' => fn (User $u) => Gate::forUser($u)->allows(PageAccess::MFI_REFERRAL_INBOX), 'active' => ['mfi.referrals.*', 'referrals.*']],

            ['key' => 'ai.assistant', 'route' => 'ai.pages.assistant', 'label' => 'المساعد الذكي', 'icon' => 'sparkles', 'group' => 'ai', 'ability' => null],
            ['key' => 'ai.escalations', 'route' => 'ai.pages.escalations.index', 'label' => 'التصعيدات', 'icon' => 'life-buoy', 'group' => 'ai', 'ability' => null, 'active' => ['ai.pages.escalations.*']],
            ['key' => 'ai.knowledge', 'route' => 'ai.pages.knowledge.published', 'label' => 'قاعدة المعرفة', 'icon' => 'book-open', 'group' => 'ai', 'ability' => null],
            ['key' => 'notifications', 'route' => 'notifications.page', 'label' => 'الإشعارات', 'icon' => 'bell', 'group' => 'account', 'ability' => null],

            ['key' => 'admin', 'route' => 'admin.index', 'label' => 'لوحة الإدارة', 'icon' => 'settings', 'group' => 'admin', 'ability' => null],
            ['key' => 'admin.users', 'route' => 'users.index', 'label' => 'المستخدمون', 'icon' => 'users', 'group' => 'admin', 'ability' => null, 'active' => ['users.*']],
            ['key' => 'admin.audit-logs', 'route' => 'admin.audit-logs.index', 'label' => 'سجل التدقيق', 'icon' => 'scroll-text', 'group' => 'admin', 'ability' => fn (User $u) => $u->can('viewAny', AuditLog::class), 'active' => ['admin.audit-logs.*']],
            ['key' => 'admin.compliance', 'route' => 'admin.compliance.index', 'label' => 'متطلبات الامتثال', 'icon' => 'shield-check', 'group' => 'admin', 'ability' => fn (User $u) => ReadinessAccess::isAdmin($u)],
            ['key' => 'admin.readiness', 'route' => 'admin.readiness.index', 'label' => 'قواعد الجاهزية', 'icon' => 'gauge', 'group' => 'admin', 'ability' => fn (User $u) => ReadinessAccess::isAdmin($u)],
            ['key' => 'admin.mfis', 'route' => 'admin.mfis.index', 'label' => 'مؤسسات التمويل الأصغر', 'icon' => 'landmark', 'group' => 'admin', 'ability' => fn (User $u) => ReadinessAccess::isAdmin($u)],
            ['key' => 'admin.knowledge', 'route' => 'ai.pages.knowledge.index', 'label' => 'إدارة قاعدة المعرفة', 'icon' => 'book-open', 'group' => 'admin', 'ability' => null],

            ['key' => 'operations.security', 'route' => 'operations.security', 'label' => 'قائمة الأمان', 'icon' => 'shield', 'group' => 'operations', 'ability' => fn (User $u) => Gate::forUser($u)->allows(PageAccess::OPERATIONS_TECHNICAL)],
            ['key' => 'operations.backups', 'route' => 'operations.backups', 'label' => 'النسخ الاحتياطي', 'icon' => 'database', 'group' => 'operations', 'ability' => fn (User $u) => Gate::forUser($u)->allows(PageAccess::OPERATIONS_TECHNICAL)],
            ['key' => 'operations.data-control', 'route' => 'operations.data-control', 'label' => 'التحكم بالبيانات', 'icon' => 'lock', 'group' => 'operations', 'ability' => fn (User $u) => Gate::forUser($u)->allows(PageAccess::OPERATIONS_DATA_CONTROL)],

            ['key' => 'account', 'route' => 'account.show', 'label' => 'حسابي', 'icon' => 'user', 'group' => 'account', 'ability' => fn (User $u) => $u->isActive()],
            ['key' => 'account.sessions', 'route' => 'account.sessions', 'label' => 'الجلسات', 'icon' => 'monitor', 'group' => 'account', 'ability' => fn (User $u) => $u->isActive()],
            ['key' => 'account.two-factor', 'route' => 'two-factor.show', 'label' => 'التحقق بخطوتين', 'icon' => 'key-round', 'group' => 'account', 'ability' => null],
        ];
    }

    /**
     * @return list<array{key: string, label: string, href: string, icon: string, group: string, active: bool}>
     */
    /**
     * `auth.can` sidebar flags: one boolean per navigation key, from the same checks as build().
     *
     * @return array<string, bool>
     */
    public function abilities(?User $user): array
    {
        $can = [];

        foreach ($this->definitions() as $item) {
            $can[$item['key']] = $user !== null && $user->isActive() && $this->resolve($user, $item) !== null;
        }

        return $can;
    }

    /** Route params when the user may open the item, otherwise null. */
    private function resolve(User $user, array $item): ?array
    {
        $route = Route::has($item['route']) ? Route::getRoutes()->getByName($item['route']) : null;

        if ($route === null || ! $this->middlewareAllows($user, $route)) {
            return null;
        }

        if ($item['ability'] !== null && ! ($item['ability'])($user)) {
            return null;
        }

        return isset($item['params']) ? ($item['params'])($user) : [];
    }

    public function build(?User $user, ?Request $request = null): array
    {
        if ($user === null || ! $user->isActive()) {
            return [];
        }

        $request ??= request();
        $items = [];

        foreach ($this->definitions() as $item) {
            $params = $this->resolve($user, $item);

            if ($params === null) {
                continue;
            }

            $items[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => route($item['route'], $params, false),
                'icon' => $item['icon'],
                'group' => $item['group'],
                'active' => $request->routeIs(...($item['active'] ?? [$item['route']])),
            ];
        }

        return $items;
    }

    /** Runs the same checks as the route's authorization middleware. */
    private function middlewareAllows(User $user, RouteDefinition $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$name, $args] = array_pad(explode(':', $middleware, 2), 2, '');

            if (($name === 'role' || $name === EnsureUserHasRole::class)
                && ! EnsureUserHasRole::allows($user, explode(',', $args))) {
                return false;
            }

            if ($name === EnsureAdminMfaAvailable::class && ! EnsureAdminMfaAvailable::allows($user)) {
                return false;
            }
        }

        return true;
    }

    /** The SME's enterprise, resolved exactly like the /transactions, /documents and /reports entry routes. */
    private function smeEnterprise(User $user): ?Enterprise
    {
        if ($user->role !== UserRole::SmeUser) {
            return null;
        }

        return $this->ownEnterprise[$user->id] ??= Enterprise::query()
            ->whereHas('members', fn ($query) => $query->whereKey($user->id))
            ->first();
    }
}
