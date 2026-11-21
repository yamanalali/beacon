import { Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { FilterChips, Pager, shortDate, withQuery, type PaginatedProps } from "../../Components/Ai/aiShared";
import { postJson } from "../../Components/Ai/http";
import { notificationHref, notificationIcon, type NotificationData } from "../../Components/Notifications/notificationDisplay";
import { useT } from "../../Hooks/useT";

export type NotificationRow = {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string | null;
};

type Props = { notifications: PaginatedProps<NotificationRow>; unread_count: number; filters: { filter?: string | null }; fetchImpl?: typeof fetch };

export default function Index({ notifications, unread_count, filters, fetchImpl }: Props) {
    const t = useT();
    const [readIds, setReadIds] = useState<Set<string>>(new Set());
    const [error, setError] = useState<string | null>(null);
    useEffect(() => { setReadIds(new Set()); }, [notifications]);
    const pendingUnread = notifications.data.filter(n => !n.read_at && readIds.has(n.id)).length;
    const unread = Math.max(0, unread_count - pendingUnread);

    async function markRead(n: NotificationRow) {
        setError(null);
        const r = await postJson(`/notifications/${n.id}/read`, null, fetchImpl);
        if (r.ok) {
            setReadIds((s) => new Set(s).add(n.id));
            router.reload({ only: ["notifications", "unread_count"], onSuccess: () => setReadIds(new Set()) });
        } else setError(r.message);
    }

    function markAllRead() {
        setError(null);
        router.post("/notifications/read-all", {}, { preserveScroll: true });
    }

    function remove(n: NotificationRow) {
        if (!window.confirm(t("notifications.delete_confirm", undefined, "حذف هذا الإشعار؟"))) {
            return;
        }
        setError(null);
        router.delete(`/notifications/${n.id}`, { preserveScroll: true });
    }

    return (
        <AppLayout title={t("notifications.title", undefined, "الإشعارات")}>
            <div className="app-page d-flex flex-column gap-3">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title={t("notifications.title", undefined, "الإشعارات")}
                        description={
                            unread > 0
                                ? t("notifications.unread_description", { count: unread }, `لديك ${unread} إشعار غير مقروء.`)
                                : t("notifications.all_read_description", undefined, "لا توجد إشعارات غير مقروءة.")
                        }
                    />
                    {unread > 0 ? (
                        <button type="button" className="btn btn-outline-primary" style={{ minHeight: "44px" }} onClick={markAllRead}>
                            <i className="bi bi-check2-all me-2" aria-hidden="true" />
                            {t("notifications.mark_all_read", undefined, "تعليم الكل كمقروء")}
                        </button>
                    ) : null}
                </div>
                <FilterChips
                    label={t("notifications.filter_label", undefined, "تصفية الإشعارات")}
                    current={filters.filter ?? null}
                    options={[
                        { value: null, label: t("common.all", undefined, "الكل") },
                        { value: "unread", label: t("notifications.unread", undefined, "غير المقروءة") },
                    ]}
                    hrefFor={(v) => withQuery("/notifications/page", { filter: v })}
                />
                {error ? (
                    <div className="alert alert-danger mb-0" role="alert">
                        {error}
                    </div>
                ) : null}
                <SectionCard>
                    {notifications.data.length === 0 ? (
                        <EmptyState
                            icon="bi-bell"
                            title={
                                filters.filter === "unread"
                                    ? t("notifications.empty_unread", undefined, "لا توجد إشعارات غير مقروءة")
                                    : t("notifications.empty_all", undefined, "لا توجد إشعارات بعد")
                            }
                        />
                    ) : (
                        <ul className="list-group list-group-flush">
                            {notifications.data.map((n) => {
                                const isRead = Boolean(n.read_at) || readIds.has(n.id);
                                const href = notificationHref(n.data);
                                return (
                                    <li key={n.id} className="list-group-item px-0 d-flex gap-3 align-items-start" data-unread={isRead ? undefined : "true"}>
                                        <span className="app-quick-link__icon flex-shrink-0" aria-hidden="true">
                                            <i className={`bi ${notificationIcon(n.data.type)}`} />
                                        </span>
                                        <div className="flex-grow-1 min-w-0">
                                            <div className="d-flex flex-wrap justify-content-between gap-2">
                                                <strong className={isRead ? "fw-normal" : undefined}>
                                                    {isRead ? null : (
                                                        <span className="visually-hidden">{t("notifications.unread_prefix", undefined, "غير مقروء: ")}</span>
                                                    )}
                                                    {n.data.title ?? t("notifications.default_title", undefined, "إشعار")}
                                                </strong>
                                                <bdi dir="ltr" className="small text-body-secondary">{shortDate(n.created_at)}</bdi>
                                            </div>
                                            {n.data.message ? <p className="mb-2 small">{n.data.message}</p> : null}
                                            <div className="d-flex flex-wrap gap-2">
                                                {href ? (
                                                    <Link href={href} className="btn btn-sm btn-outline-secondary">
                                                        {t("notifications.open", undefined, "فتح")}
                                                    </Link>
                                                ) : null}
                                                {!isRead ? (
                                                    <button type="button" className="btn btn-sm btn-link px-0" onClick={() => markRead(n)}>
                                                        {t("notifications.mark_read", undefined, "تعليم كمقروء")}
                                                    </button>
                                                ) : null}
                                                <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => remove(n)}>
                                                    {t("notifications.delete", undefined, "حذف")}
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <Pager meta={notifications.meta} links={notifications.links} />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
