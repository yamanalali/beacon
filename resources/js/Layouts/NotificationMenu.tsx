import { Link, router, usePage } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import { notificationHref, notificationIcon } from "../Components/Notifications/notificationDisplay";
import { postJson } from "../Components/Ai/http";
import { shortDate } from "../Components/Ai/aiShared";

type RecentItem = {
    id: string;
    data: {
        type?: string;
        title?: string;
        message?: string;
        enterprise_public_id?: string | null;
        escalation_public_id?: string | null;
        [k: string]: unknown;
    };
    read_at: string | null;
    created_at: string | null;
};

export function NotificationMenu() {
    const {
        props: { auth },
        url,
    } = usePage();
    const menu = useRef<HTMLDetailsElement>(null);
    const unread = auth.notifications?.unread_count ?? 0;
    const recent = auth.notifications?.recent ?? [];
    const badge = unread > 99 ? "99+" : String(unread);

    useEffect(() => {
        if (menu.current) menu.current.open = false;
    }, [url]);

    useEffect(() => {
        const dismiss = (e: PointerEvent) => {
            if (menu.current && !menu.current.contains(e.target as Node)) {
                menu.current.open = false;
            }
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

    async function openItem(item: RecentItem) {
        if (!item.read_at) {
            await postJson(`/notifications/${item.id}/read`, null);
        }
        const href = notificationHref(item.data) ?? "/notifications/page";
        menu.current && (menu.current.open = false);
        router.visit(href);
    }

    return (
        <details ref={menu} className="app-notification-menu">
            <summary
                className="app-icon-button app-notification-menu__trigger"
                aria-label={unread > 0 ? `الإشعارات، ${unread} غير مقروء` : "الإشعارات"}
            >
                <i className="bi bi-bell" aria-hidden="true" />
                {unread > 0 ? (
                    <span className="app-notification-badge" aria-hidden="true">
                        {badge}
                    </span>
                ) : null}
            </summary>
            <div className="app-notification-menu__panel" role="menu" aria-label="قائمة الإشعارات">
                <div className="app-notification-menu__heading">
                    <strong>الإشعارات</strong>
                    {unread > 0 ? <span className="app-notification-menu__count">{unread} غير مقروء</span> : null}
                </div>
                {recent.length === 0 ? (
                    <p className="app-notification-menu__empty">لا توجد إشعارات بعد.</p>
                ) : (
                    <ul className="app-notification-menu__list">
                        {recent.map((item) => {
                            const unreadItem = !item.read_at;
                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        className={`app-notification-menu__item${unreadItem ? " is-unread" : ""}`}
                                        role="menuitem"
                                        onClick={() => void openItem(item)}
                                    >
                                        <span className="app-notification-menu__icon" aria-hidden="true">
                                            <i className={`bi ${notificationIcon(item.data.type)}`} />
                                        </span>
                                        <span className="app-notification-menu__body">
                                            <span className="app-notification-menu__title">
                                                {unreadItem ? <span className="visually-hidden">غير مقروء: </span> : null}
                                                {item.data.title ?? "إشعار"}
                                            </span>
                                            {item.data.message ? (
                                                <span className="app-notification-menu__message">{item.data.message}</span>
                                            ) : null}
                                            <bdi dir="ltr" className="app-notification-menu__time">
                                                {shortDate(item.created_at)}
                                            </bdi>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <Link href="/notifications/page" className="app-notification-menu__all">
                    فتح صفحة الإشعارات
                </Link>
            </div>
        </details>
    );
}
