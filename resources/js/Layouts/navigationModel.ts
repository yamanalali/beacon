import type { AuthCan, NavigationItem } from "../types/shared";

/**
 * Presentation-only model for `auth.navigation`. The server decides WHICH links a user sees
 * (route middleware, policies, page gates); this file only decides how they look and are ordered.
 * No role logic here.
 */

export type NavGroupView = { key: string; labelKey: string | null; items: NavigationItem[] };

/** Group order and headings (keyed by the stable `group` value). Unknown groups go last, untitled. */
export const GROUP_ORDER = ["main", "enterprise", "mfi", "ai", "admin", "operations", "account"] as const;
/** i18n keys resolved where group labels are rendered (e.g. DesktopSidebar). */
export const GROUP_LABEL_KEYS: Record<string, string | null> = {
    main: null,
    enterprise: "nav.group.enterprise",
    mfi: "nav.group.mfi",
    ai: "nav.group.ai",
    admin: "nav.group.admin",
    operations: "nav.group.operations",
    account: "nav.group.account",
};

/** Bootstrap icon per stable item key; then per server icon name; then a neutral fallback. */
export const KEY_ICONS: Record<string, string> = {
    "dashboard.sme": "bi-house-door",
    "dashboard.beacon": "bi-house-door",
    enterprises: "bi-buildings",
    transactions: "bi-cash-stack",
    documents: "bi-folder2",
    reports: "bi-file-earmark-text",
    readiness: "bi-shield-check",
    compliance: "bi-patch-check",
    referrals: "bi-send",
    "mfi.referrals": "bi-inbox",
    "ai.assistant": "bi-chat-dots",
    "ai.escalations": "bi-life-preserver",
    "ai.knowledge": "bi-book",
    notifications: "bi-bell",
    admin: "bi-gear",
    "admin.users": "bi-people",
    "admin.audit-logs": "bi-journal-text",
    "admin.compliance": "bi-patch-check",
    "admin.readiness": "bi-sliders",
    "admin.mfis": "bi-bank",
    "admin.knowledge": "bi-journal-bookmark",
    "operations.security": "bi-shield-lock",
    "operations.backups": "bi-database-check",
    "operations.data-control": "bi-lock",
    account: "bi-person",
    "account.sessions": "bi-display",
    "account.two-factor": "bi-key",
};
const SERVER_ICONS: Record<string, string> = {
    "layout-dashboard": "bi-house-door", building: "bi-buildings", receipt: "bi-cash-stack", "file-text": "bi-file-earmark-text",
    "bar-chart": "bi-bar-chart", gauge: "bi-speedometer2", "shield-check": "bi-shield-check", send: "bi-send", inbox: "bi-inbox",
    sparkles: "bi-stars", "life-buoy": "bi-life-preserver", "book-open": "bi-book", bell: "bi-bell", settings: "bi-gear",
    users: "bi-people", "scroll-text": "bi-journal-text", landmark: "bi-bank", shield: "bi-shield", database: "bi-database",
    lock: "bi-lock", user: "bi-person", monitor: "bi-display", "key-round": "bi-key",
};
export function iconFor(item: Pick<NavigationItem, "key" | "icon">): string {
    return KEY_ICONS[item.key] ?? SERVER_ICONS[item.icon] ?? "bi-circle";
}

/** Order inside a group by stable key; unknown keys keep the server order after known ones. */
export const KEY_ORDER = Object.keys(KEY_ICONS);
function rank(key: string): number {
    const i = KEY_ORDER.indexOf(key);
    return i === -1 ? KEY_ORDER.length : i;
}

/** Items that are the "home" for the user (rendered as the Home link, not repeated in lists). */
export const isHomeItem = (item: NavigationItem) => item.group === "main";

/** Account links (notifications / profile / sessions) live in the topbar menus only. */
export const isShellAccountItem = (item: NavigationItem) => item.group === "account";

export function groupNavigation(items: NavigationItem[] | null | undefined): NavGroupView[] {
    const list = (items ?? []).filter((i) => !isHomeItem(i) && !isShellAccountItem(i));
    const keys = [...new Set(list.map((i) => i.group))].sort((a, b) => {
        const ia = (GROUP_ORDER as readonly string[]).indexOf(a);
        const ib = (GROUP_ORDER as readonly string[]).indexOf(b);
        return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
    });
    return keys.map((g) => ({
        key: g,
        labelKey: GROUP_LABEL_KEYS[g] ?? null,
        items: list
            .map((item, i) => ({ item, i }))
            .filter((x) => x.item.group === g)
            .sort((a, b) => rank(a.item.key) - rank(b.item.key) || a.i - b.i)
            .map((x) => x.item),
    }));
}

/** Home link: the server's dashboard item if present, otherwise `/` (which redirects by role). */
export function homeLink(items: NavigationItem[] | null | undefined, url: string): { href: string; active: boolean } {
    const home = (items ?? []).find(isHomeItem);
    const path = url.split("?")[0] ?? "/";
    return { href: home?.href ?? "/", active: home ? home.active || path === "/" : path === "/" || path.startsWith("/dashboard/") };
}

/** Mobile bottom tabs: up to 3 by key priority among the items the server sent; the rest go to "More". */
/** Bottom tabs (with Home + More): prefer المعاملات then التقارير; docs/readiness/compliance/referrals stay in More. */
export const MOBILE_TAB_PRIORITY = [
    "transactions", "reports", "enterprises", "mfi.referrals",
    "operations.security", "operations.backups", "operations.data-control",
    "ai.escalations", "ai.assistant",
];
export function splitMobile(items: NavigationItem[] | null | undefined, max = 3): { tabs: NavigationItem[]; more: NavigationItem[] } {
    const list = (items ?? []).filter((i) => !isHomeItem(i) && !isShellAccountItem(i));
    const tabs = MOBILE_TAB_PRIORITY.map((k) => list.find((i) => i.key === k)).filter((i): i is NavigationItem => !!i).slice(0, max);
    const more = groupNavigation(list).flatMap((g) => g.items).filter((i) => !tabs.includes(i));
    return { tabs, more };
}

/** Fallback when the prop is missing (e.g. guest-shared layout): empty — account links stay in the topbar menus. */
export const FALLBACK_NAVIGATION: NavigationItem[] = [];
export function navigationOrFallback(
    items: NavigationItem[] | null | undefined,
    url: string,
    can?: Partial<AuthCan> | Record<string, boolean> | null,
): NavigationItem[] {
    if (Array.isArray(items)) return filterByCan(items, can);
    const path = url.split("?")[0] ?? "";
    return FALLBACK_NAVIGATION.map((i) => ({ ...i, active: path === i.href || path.startsWith(`${i.href}/`) }));
}

/** Keep only items whose `auth.can[key]` flag is true. Without a `can` map, the server list is kept as-is. */
export function filterByCan(items: NavigationItem[], can?: Partial<AuthCan> | Record<string, boolean> | null): NavigationItem[] {
    if (!can) return items;
    const flags = can as Record<string, boolean | undefined>;
    return items.filter((item) => flags[item.key] === true);
}

/** Page shortcut links (dashboards): hide `/transactions`, `/documents`, `/reports`, `/enterprises` when the matching `auth.can` flag is false. */
const LINK_KEYS: Record<string, string> = { "/enterprises": "enterprises", "/transactions": "transactions", "/documents": "documents", "/reports": "reports" };
export function visibleLinks<T extends { href: string }>(can: Partial<AuthCan> | Record<string, boolean> | null | undefined, links: T[]): T[] {
    if (!can) return links;
    const flags = can as Record<string, boolean | undefined>;
    return links.filter((l) => (LINK_KEYS[l.href] ? flags[LINK_KEYS[l.href]] === true : true));
}
