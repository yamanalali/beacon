export type NavItem = {
    key: string;
    label: string;
    icon: string;
    href?: string;
};

/** Select the current module without also selecting its parent enterprise directory. */
export function isNavItemActive(item: NavItem, url: string): boolean {
    if (!item.href) return false;
    const path = url.split("?")[0] ?? "";
    if (path === item.href) return true;
    if (item.key === "enterprises")
        return /^\/enterprises\/[^/]+(?:\/edit)?$/.test(path);
    if (item.key === "enterprise-profile") return path === `${item.href}/edit`;
    if (["transactions", "reports", "documents"].includes(item.key))
        return path.split("/").includes(item.key);
    if (item.key === "readiness")
        return (
            path.startsWith("/readiness-assessments/") ||
            path.startsWith(`${item.href}/`)
        );
    return item.href !== "/admin" && path.startsWith(`${item.href}/`);
}

export function isHomePath(url: string): boolean {
    const path = url.split("?")[0] ?? "/";

    return path === "/" || path === "" || path.startsWith("/dashboard/");
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
    {
        key: "transactions",
        label: "المعاملات",
        icon: "bi-cash-stack",
        href: "/transactions",
    },
    {
        key: "reports",
        label: "التقارير",
        icon: "bi-file-earmark-text",
        href: "/reports",
    },
    { key: "readiness", label: "الجاهزية", icon: "bi-shield-check" },
];

export const moreSoonItems: NavItem[] = [
    { key: "compliance", label: "الامتثال", icon: "bi-patch-check" },
    {
        key: "documents",
        label: "المستندات",
        icon: "bi-folder2",
        href: "/documents",
    },
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
            {
                key: "transactions",
                label: "المعاملات",
                icon: "bi-cash-stack",
                href: "/transactions",
            },
            {
                key: "reports",
                label: "التقارير",
                icon: "bi-file-earmark-text",
                href: "/reports",
            },
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
    {
        key: "documents",
        label: "المستندات",
        icon: "bi-folder2",
        href: "/documents",
    },
    { key: "assistant", label: "المساعد", icon: "bi-chat-dots" },
];

/** Shown only to Beacon admins (UI visibility only; routes enforce role server-side). */
export const adminItem: NavItem = {
    key: "admin",
    label: "الإدارة",
    icon: "bi-gear",
    href: "/admin",
};

export function workspaceGroups(
    role: string | null | undefined,
    enterpriseId?: string,
): { label: string; items: NavItem[] }[] {
    if (role === "technical_admin")
        return [
            {
                label: "إدارة النظام",
                items: [
                    {
                        key: "security",
                        label: "الأمان",
                        icon: "bi-shield-check",
                        href: "/operations/security",
                    },
                    {
                        key: "backups",
                        label: "النسخ الاحتياطي",
                        icon: "bi-database-check",
                        href: "/operations/backups",
                    },
                    {
                        key: "data-control",
                        label: "حماية البيانات",
                        icon: "bi-lock",
                        href: "/operations/data-control",
                    },
                ],
            },
        ];
    if (role === "mfi_user")
        return [
            {
                label: "التمويل",
                items: [
                    {
                        key: "referrals",
                        label: "الإحالات الواردة",
                        icon: "bi-inbox",
                        href: "/mfi/referrals",
                    },
                ],
            },
        ];
    const enterprise = enterpriseId
        ? `/enterprises/${enterpriseId}`
        : undefined;
    const groups = [
        {
            label: "مساحة العمل",
            items:
                role === "sme_user"
                    ? enterprise
                        ? [
                              {
                                  key: "enterprise-profile",
                                  label: "ملف المنشأة",
                                  icon: "bi-shop",
                                  href: enterprise,
                              },
                          ]
                        : []
                    : [
                          {
                              key: "enterprises",
                              label: "المنشآت",
                              icon: "bi-buildings",
                              href: "/enterprises",
                          },
                      ],
        },
        ...desktopGroups.map((group) => ({
            ...group,
            items: group.items.map((item) => ({
                ...item,
                href:
                    enterprise &&
                    ["readiness", "compliance", "referrals"].includes(item.key)
                        ? `${enterprise}/${item.key}`
                        : item.href,
            })),
        })),
        { label: "الملفات والدعم", items: desktopSingleItems },
    ];
    if (role === "beacon_admin")
        groups.push({
            label: "إدارة البرنامج",
            items: [
                adminItem,
                {
                    key: "users",
                    label: "المستخدمون",
                    icon: "bi-people",
                    href: "/users",
                },
                {
                    key: "compliance-settings",
                    label: "متطلبات الامتثال",
                    icon: "bi-patch-check",
                    href: "/admin/compliance",
                },
                {
                    key: "readiness-settings",
                    label: "معايير الجاهزية",
                    icon: "bi-sliders",
                    href: "/admin/readiness",
                },
                {
                    key: "mfis",
                    label: "مؤسسات التمويل",
                    icon: "bi-bank",
                    href: "/admin/mfis",
                },
                {
                    key: "audit-logs",
                    label: "سجل التدقيق",
                    icon: "bi-journal-text",
                    href: "/admin/audit-logs",
                },
            ],
        });
    return groups.filter((group) => group.items.length > 0);
}

export const roleLabels: Record<string, string> = {
    beacon_admin: "إدارة البرنامج",
    beacon_advisor: "مستشار بيكون",
    sme_user: "صاحب منشأة",
    mfi_user: "مؤسسة تمويل",
    technical_admin: "الإدارة التقنية",
};
