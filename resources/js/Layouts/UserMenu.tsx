import { confirmOfflineLogout } from "../lib/offline";
import { clearDeviceDrafts } from "../lib/offlineDrafts";
import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import { roleLabels } from "./navigation";

export function UserMenu({ compact = false }: { compact?: boolean }) {
    const {
        props: { auth },
        url,
    } = usePage();
    const menu = useRef<HTMLDetailsElement>(null);
    const name = auth.user?.name.trim() || "زائر";
    const role = roleLabels[auth.user?.role ?? ""] ?? "مساحة العمل";
    const unread = auth.notifications?.unread_count ?? 0;
    const badge = unread > 99 ? "99+" : String(unread);
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
                aria-label={
                    unread > 0
                        ? `قائمة الحساب، ${name}، ${unread} إشعار غير مقروء`
                        : `قائمة الحساب، ${name}`
                }
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
                        الإشعارات
                    </span>
                    {unread > 0 ? (
                        <span className="app-notification-badge app-notification-badge--inline" aria-label={`${unread} غير مقروء`}>
                            {badge}
                        </span>
                    ) : null}
                </Link>
                <Link href="/account" className="dropdown-item">
                    <i className="bi bi-person-gear" aria-hidden="true" />
                    حسابي
                </Link>
                <Link href="/account/sessions" className="dropdown-item">
                    <i className="bi bi-laptop" aria-hidden="true" />
                    جلسات الدخول
                </Link>
                <Link
                    onBefore={() => { if (!confirmOfflineLogout()) return false; clearDeviceDrafts(); }}
                    href="/logout"
                    method="post"
                    as="button"
                    className="dropdown-item text-danger"
                >
                    <i className="bi bi-box-arrow-left" aria-hidden="true" />
                    تسجيل الخروج
                </Link>
            </div>
        </details>
    );
}
