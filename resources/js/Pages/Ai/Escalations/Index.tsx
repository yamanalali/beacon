import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { DataTable, type Column } from "../../../Components/Data/DataTable";
import { EscalationStatus, FilterChips, shortDate, withQuery, type PaginatedProps } from "../../../Components/Ai/aiShared";

export type EscalationRow = { id: string; question: string; status: string; created_at: string | null };

const columns: Column<EscalationRow>[] = [
    {
        key: "question",
        header: "السؤال",
        primary: true,
        cell: (e) => (
            <Link href={`/ai/pages/escalations/${e.id}`} className="fw-semibold">
                {e.question.length > 120 ? `${e.question.slice(0, 120)}…` : e.question}
            </Link>
        ),
    },
    { key: "status", header: "الحالة", cell: (e) => <EscalationStatus status={e.status} /> },
    { key: "created_at", header: "التاريخ", numeric: true, cell: (e) => <bdi dir="ltr">{shortDate(e.created_at)}</bdi> },
];

type Props = { escalations: PaginatedProps<EscalationRow>; filters: { status?: string | null }; can_escalate: boolean };

export default function Index({ escalations, filters, can_escalate }: Props) {
    return (
        <AppLayout title="الاستفسارات">
            <div className="app-page d-flex flex-column gap-3">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader title="الاستفسارات" description="الأسئلة المرسلة إلى فريق بيكون وحالتها." />
                    {can_escalate ? (
                        <Link href="/ai/pages/assistant" className="btn btn-primary">
                            <i className="bi bi-stars me-2" aria-hidden="true" />
                            اسأل المساعد
                        </Link>
                    ) : null}
                </div>
                <FilterChips
                    label="تصفية حسب الحالة"
                    current={filters.status ?? null}
                    options={[
                        { value: null, label: "الكل" },
                        { value: "open", label: "مفتوحة" },
                    ]}
                    hrefFor={(v) => withQuery("/ai/pages/escalations", { status: v })}
                />
                <SectionCard title="قائمة الاستفسارات">
                    <DataTable
                        caption="الاستفسارات"
                        columns={columns}
                        rowKey={(e) => e.id}
                        emptyTitle={filters.status ? "لا توجد استفسارات بهذه الحالة" : "لا توجد استفسارات بعد"}
                        emptyDescription="عندما ترسل سؤالاً لفريق بيكون سيظهر هنا."
                        server={{ paginator: escalations, only: ["escalations"], query: { status: filters.status ?? undefined } }}
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
