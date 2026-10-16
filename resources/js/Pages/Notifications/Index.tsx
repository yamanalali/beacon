import { Link, router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { Pagination } from "../../Components/Data/Pagination";
import type { Paginator } from "../../Components/Data/DataTable";
import { useJsonAction } from "../../hooks/useJsonAction";
import { displayDate } from "../Ai/shared";

type Notification = {
    id: string;
    data: {
        title?: string;
        message?: string;
        type?: string;
        enterprise_public_id?: string;
    };
    read_at: string | null;
    created_at: string | null;
};
export default function Index({
    notifications,
    unread_count,
    filters,
}: {
    notifications: Paginator<Notification>;
    unread_count: number;
    filters: { filter: string | null };
}) {
    const { request, busy, error } = useJsonAction();
    const links =
        "meta" in notifications
            ? (notifications.meta.links ?? [])
            : notifications.links;
    return (
        <AppLayout title="الإشعارات">
            <div className="app-page">
                <PageHeader
                    title="الإشعارات"
                    description={
                        unread_count
                            ? `لديك ${unread_count} إشعار غير مقروء.`
                            : "تابع التذكيرات والتحديثات الخاصة بك."
                    }
                />
                <nav className="d-flex gap-2 mb-3" aria-label="تصفية الإشعارات">
                    <Link
                        href="/notifications/page"
                        className={`btn ${filters.filter !== "unread" ? "btn-primary" : "btn-light"}`}
                        aria-current={
                            filters.filter !== "unread" ? "page" : undefined
                        }
                    >
                        الكل
                    </Link>
                    <Link
                        href="/notifications/page?filter=unread"
                        className={`btn ${filters.filter === "unread" ? "btn-primary" : "btn-light"}`}
                        aria-current={
                            filters.filter === "unread" ? "page" : undefined
                        }
                    >
                        غير المقروءة ({unread_count})
                    </Link>
                </nav>
                {error ? (
                    <div className="alert alert-danger" role="alert">
                        {error}
                    </div>
                ) : null}
                <SectionCard>
                    {notifications.data.length ? (
                        <ul className="list-unstyled mb-0 d-flex flex-column gap-3">
                            {notifications.data.map((n) => (
                                <li
                                    key={n.id}
                                    className={`border rounded-3 p-3 ${!n.read_at ? "border-primary-subtle bg-primary-subtle" : ""}`}
                                >
                                    <div className="d-flex flex-wrap justify-content-between gap-2">
                                        <h2 className="h6 mb-0">
                                            {n.data.title ?? "إشعار جديد"}
                                            {!n.read_at ? (
                                                <span className="badge text-bg-primary ms-2">
                                                    جديد
                                                </span>
                                            ) : null}
                                        </h2>
                                        <span className="small text-body-secondary">
                                            {displayDate(n.created_at)}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-break">
                                        {n.data.message ?? ""}
                                    </p>
                                    <div className="d-flex flex-wrap gap-2">
                                        {n.data.type ===
                                            "compliance_deadline" &&
                                        n.data.enterprise_public_id ? (
                                            <Link
                                                href={`/enterprises/${encodeURIComponent(n.data.enterprise_public_id)}/compliance`}
                                                className="btn btn-sm btn-outline-primary"
                                            >
                                                متابعة الامتثال
                                            </Link>
                                        ) : null}
                                        {!n.read_at ? (
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-light"
                                                disabled={busy}
                                                onClick={async () => {
                                                    if (
                                                        await request(
                                                            "post",
                                                            `/notifications/${n.id}/read`,
                                                        )
                                                    )
                                                        router.reload({
                                                            only: [
                                                                "notifications",
                                                                "unread_count",
                                                            ],
                                                        });
                                                }}
                                            >
                                                تحديد كمقروء
                                            </button>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState
                            title={
                                filters.filter === "unread"
                                    ? "كل إشعاراتك مقروءة"
                                    : "لا توجد إشعارات بعد"
                            }
                        />
                    )}
                    <Pagination links={links} />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
