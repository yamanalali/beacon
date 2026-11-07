import { confirmOfflineLogout } from "../lib/offline";
import { clearDeviceDrafts } from "../lib/offlineDrafts";
import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import { useT } from "../Hooks/useT";

const ROLE_KEYS: Record<string, string> = {
    beacon_admin: "role.beacon_admin",
    beacon_advisor: "role.beacon_advisor",
    sme_user: "role.sme_user",
    mfi_user: "role.mfi_user",
    technical_admin: "role.technical_admin",
};

export function UserMenu({ compact = false }: { compact?: boolean }) {
    const {
        props: { auth },
        url,
    } = usePage();
    const t = useT();
    const menu = useRef<HTMLDetailsElement>(null);
    const name = auth.user?.name.trim() || t("shell.guest_user");
    const roleKey = ROLE_KEYS[auth.user?.role ?? ""];
    const role = roleKey ? t(roleKey) : t("role.workspace");
    const unread = auth.notifications?.unread_count ?? 0;
    const badge = unread > 99 ? "99+" : String(unread);
    const menuAria =
        unread > 0
            ? t("shell.account_menu_unread", { name, count: unread })
            : t("shell.account_menu", { name });

    useEffect(() => {
        if (menu.current) menu.current.open = false;
    }, [url]);
    useEffect(() => {
        const dismiss = (e: PointerEvent) => {
            if (menu.current && !menu.current.contains(e.target as Node))
                menu.current.open = false;
        };
        const escape = (e: KeyboardEvent) => {
            if (e.key === "Escape" && menu.current?.open) {
                menu.current.open = false;
                menu.current.querySelector("summary")?.focus();
            }
        };
        document.addEventListener("pointerdown", dismiss);
        document.addEventListener("keydown", escape);
        return () => {
            document.removeEventListener("pointerdown", dismiss);
            document.removeEventListener("keydown", escape);
        };
    }, []);
    return (
        <details ref={menu} className="app-user-menu">
            <summary
                className={
                    compact ? "app-profile app-profile--compact" : "app-profile"
                }
                aria-label={menuAria}
            >
                <span className="app-avatar-wrap">
                    <span className="app-avatar" aria-hidden="true">
                        {name.charAt(0)}
                    </span>
                    {unread > 0 ? (
                        <span className="app-notification-badge app-notification-badge--avatar" aria-hidden="true">
                            {badge}
                        </span>
                    ) : null}
                </span>
                {compact ? null : (
                    <>
                        <span className="app-profile__copy">
                            <span className="app-profile__name">{name}</span>
                            <span className="app-profile__role">{role}</span>
                        </span>
                        <i
                            className="bi bi-chevron-down small"
                            aria-hidden="true"
                        />
                    </>
                )}
            </summary>
            <div className="app-profile-menu">
                <div className="app-profile-menu__heading">
                    <strong>{name}</strong>
                    <span>{role}</span>
                </div>
                <Link href="/notifications/page" className="dropdown-item d-flex align-items-center justify-content-between gap-2">
                    <span className="d-inline-flex align-items-center gap-2">
                        <i className="bi bi-bell" aria-hidden="true" />
                        {t("common.notifications")}
                    </span>
                    {unread > 0 ? (
                        <span className="app-notification-badge app-notification-badge--inline" aria-label={t("shell.unread_count", { count: unread })}>
                            {badge}
                        </span>
                    ) : null}
                </Link>
                <Link href="/account" className="dropdown-item">
                    <i className="bi bi-person-gear" aria-hidden="true" />
                    {t("common.account")}
                </Link>
                <Link href="/account/sessions" className="dropdown-item">
                    <i className="bi bi-laptop" aria-hidden="true" />
                    {t("nav.account.sessions")}
                </Link>
                <Link
                    onBefore={() => { if (!confirmOfflineLogout()) return false; clearDeviceDrafts(); }}
                    href="/logout"
                    method="post"
                    as="button"
                    className="dropdown-item text-danger"
                >
                    <i className="bi bi-box-arrow-left" aria-hidden="true" />
                    {t("common.logout")}
                </Link>
            </div>
        </details>
    );
}
