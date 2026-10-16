import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { DataTable, type Paginator } from "../../../Components/Data/DataTable";
import { StatusBadge } from "../../../Components/Feedback/StatusBadge";
import { displayDate, statusLabels, type Escalation } from "../shared";

export default function Index({
    escalations,
    filters,
    can_escalate,
}: {
    escalations: Paginator<Escalation>;
    filters: { status: string | null };
    can_escalate: boolean;
}) {
    return (
        <AppLayout title="الاستفسارات">
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title="الاستفسارات والمتابعة"
                        description="تابع الأسئلة المرسلة إلى فريق بيكون."
                    />
                    {can_escalate ? (
                        <Link
                            href="/ai/pages/assistant"
                            className="btn btn-primary"
                        >
                            استفسار جديد
                        </Link>
                    ) : null}
                </div>
                <nav
                    className="d-flex gap-2 mb-3"
                    aria-label="تصفية الاستفسارات"
                >
                    <Link
                        href="/ai/pages/escalations"
                        className={`btn ${!filters.status ? "btn-primary" : "btn-light"}`}
                    >
                        الكل
                    </Link>
                    <Link
                        href="/ai/pages/escalations?status=open"
                        className={`btn ${filters.status === "open" ? "btn-primary" : "btn-light"}`}
                    >
                        المفتوحة
                    </Link>
                </nav>
                <SectionCard>
                    <DataTable
                        columns={[
                            {
                                key: "question",
                                header: "السؤال",
                                primary: true,
                                cell: (e) => (
                                    <Link
                                        href={`/ai/pages/escalations/${e.id}`}
                                        className="text-break"
                                    >
                                        {e.question}
                                    </Link>
                                ),
                            },
                            {
                                key: "status",
                                header: "الحالة",
                                cell: (e) => (
                                    <StatusBadge
                                        label={
                                            statusLabels[e.status] ?? e.status
                                        }
                                        tone="warning"
                                    />
                                ),
                            },
                            {
                                key: "created_at",
                                header: "تاريخ الإرسال",
                                cell: (e) => displayDate(e.created_at),
                            },
                        ]}
                        rowKey={(e) => e.id}
                        server={{ paginator: escalations, query: filters }}
                        emptyTitle="لا توجد استفسارات بعد"
                        caption="الاستفسارات المرسلة"
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
