import { Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { FilterChips, Pager, shortDate, withQuery, type PaginatedProps } from "../../Components/Ai/aiShared";
import { postJson } from "../../Components/Ai/http";

export type NotificationRow = {
    id: string;
    data: { type?: string; title?: string; message?: string; enterprise_public_id?: string | null; due_date?: string | null; [k: string]: unknown };
    read_at: string | null;
    created_at: string | null;
};

type Props = { notifications: PaginatedProps<NotificationRow>; unread_count: number; filters: { filter?: string | null }; fetchImpl?: typeof fetch };

const ICONS: Record<string, string> = { compliance_deadline: "bi-calendar-event" };

function targetHref(n: NotificationRow): string | null {
    if (n.data.type === "compliance_deadline" && n.data.enterprise_public_id) return `/enterprises/${n.data.enterprise_public_id}/compliance`;
    return null;
}

export default function Index({ notifications, unread_count, filters, fetchImpl }: Props) {
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

    return (
        <AppLayout title="الإشعارات">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="الإشعارات" description={unread > 0 ? `لديك ${unread} إشعار غير مقروء.` : "لا توجد إشعارات غير مقروءة."} />
                <FilterChips
                    label="تصفية الإشعارات"
                    current={filters.filter ?? null}
                    options={[
                        { value: null, label: "الكل" },
                        { value: "unread", label: "غير المقروءة" },
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
                        <EmptyState icon="bi-bell" title={filters.filter === "unread" ? "لا توجد إشعارات غير مقروءة" : "لا توجد إشعارات بعد"} />
                    ) : (
                        <ul className="list-group list-group-flush">
                            {notifications.data.map((n) => {
                                const isRead = Boolean(n.read_at) || readIds.has(n.id);
                                const href = targetHref(n);
                                return (
                                    <li key={n.id} className="list-group-item px-0 d-flex gap-3 align-items-start" data-unread={isRead ? undefined : "true"}>
                                        <span className="app-quick-link__icon flex-shrink-0" aria-hidden="true">
                                            <i className={`bi ${ICONS[n.data.type ?? ""] ?? "bi-bell"}`} />
                                        </span>
                                        <div className="flex-grow-1 min-w-0">
                                            <div className="d-flex flex-wrap justify-content-between gap-2">
                                                <strong className={isRead ? "fw-normal" : undefined}>
                                                    {isRead ? null : <span className="visually-hidden">غير مقروء: </span>}
                                                    {n.data.title ?? "إشعار"}
                                                </strong>
                                                <bdi dir="ltr" className="small text-body-secondary">{shortDate(n.created_at)}</bdi>
                                            </div>
                                            {n.data.message ? <p className="mb-2 small">{n.data.message}</p> : null}
                                            <div className="d-flex flex-wrap gap-2">
                                                {href ? (
                                                    <Link href={href} className="btn btn-sm btn-outline-secondary">
                                                        فتح
                                                    </Link>
                                                ) : null}
                                                {!isRead ? (
                                                    <button type="button" className="btn btn-sm btn-link px-0" onClick={() => markRead(n)}>
                                                        تعليم كمقروء
                                                    </button>
                                                ) : null}
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
