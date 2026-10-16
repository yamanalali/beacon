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

export const roleLabels: Record<string, string> = {
    beacon_admin: "إدارة البرنامج",
    beacon_advisor: "مستشار بيكون",
    sme_user: "صاحب منشأة",
    mfi_user: "مؤسسة تمويل",
    technical_admin: "الإدارة التقنية",
};
