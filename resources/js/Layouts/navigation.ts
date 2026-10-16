export type NavItem = {
    key: string;
    label: string;
    icon: string;
    href?: string;
};

export function isHomePath(url: string): boolean {
    const path = url.split("?")[0] ?? "/";

    return path === "/" || path === "";
}

export function isAccountPath(url: string): boolean {
    const path = url.split("?")[0] ?? "";

    return path === "/account" || path.startsWith("/account/");
}

export const homeItem: NavItem = {
    key: "home",
    label: "الرئيسية",
    icon: "bi-house-door",
};

export const mobileSoonItems: NavItem[] = [
    { key: "transactions", label: "المعاملات", icon: "bi-cash-stack", href: "/transactions" },
    { key: "reports", label: "التقارير", icon: "bi-file-earmark-text", href: "/reports" },
    { key: "readiness", label: "الجاهزية", icon: "bi-shield-check" },
];

export const moreSoonItems: NavItem[] = [
    { key: "compliance", label: "الامتثال", icon: "bi-patch-check" },
    { key: "documents", label: "المستندات", icon: "bi-folder2", href: "/documents" },
    { key: "assistant", label: "المساعد", icon: "bi-chat-dots" },
    { key: "referrals", label: "الإحالات", icon: "bi-send" },
];

export const accountItem: NavItem = {
    key: "account",
    label: "حسابي",
    icon: "bi-person",
};

export const desktopGroups: { label: string; items: NavItem[] }[] = [
    {
        label: "المالية",
        items: [
            { key: "transactions", label: "المعاملات", icon: "bi-cash-stack", href: "/transactions" },
            { key: "reports", label: "التقارير", icon: "bi-file-earmark-text", href: "/reports" },
        ],
    },
    {
        label: "الجاهزية والامتثال",
        items: [
            { key: "compliance", label: "الامتثال", icon: "bi-patch-check" },
            { key: "readiness", label: "الجاهزية", icon: "bi-shield-check" },
            { key: "referrals", label: "الإحالات", icon: "bi-send" },
        ],
    },
];

export const desktopSingleItems: NavItem[] = [
    { key: "documents", label: "المستندات", icon: "bi-folder2", href: "/documents" },
    { key: "assistant", label: "المساعد", icon: "bi-chat-dots" },
];

/** Shown only to Beacon admins (UI visibility only; routes enforce role server-side). */
export const adminItem: NavItem = {
    key: "admin",
    label: "الإدارة",
    icon: "bi-gear",
    href: "/admin",
};
