import { Link } from "@inertiajs/react";
import { useMemo } from "react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { DataTable, type Column } from "../../../Components/Data/DataTable";
import { EscalationStatus, FilterChips, shortDate, withQuery, type PaginatedProps } from "../../../Components/Ai/aiShared";
import { useT } from "../../../Hooks/useT";

export type EscalationRow = { id: string; question: string; status: string; created_at: string | null };

function useEscalationColumns(t: ReturnType<typeof useT>): Column<EscalationRow>[] {
    return useMemo(
        () => [
            {
                key: "question",
                header: t("ai.escalations.column.question", undefined, "السؤال"),
                primary: true,
                cell: (e) => (
                    <Link href={`/ai/pages/escalations/${e.id}`} className="fw-semibold">
                        {e.question.length > 120 ? `${e.question.slice(0, 120)}…` : e.question}
                    </Link>
                ),
            },
            { key: "status", header: t("common.status", undefined, "الحالة"), cell: (e) => <EscalationStatus status={e.status} /> },
            {
                key: "created_at",
                header: t("common.date", undefined, "التاريخ"),
                numeric: true,
                cell: (e) => <bdi dir="ltr">{shortDate(e.created_at)}</bdi>,
            },
        ],
        [t],
    );
}

type Props = { escalations: PaginatedProps<EscalationRow>; filters: { status?: string | null }; can_escalate: boolean };

export default function Index({ escalations, filters, can_escalate }: Props) {
    const t = useT();
    const columns = useEscalationColumns(t);

    return (
        <AppLayout title={t("ai.escalations.title", undefined, "الاستفسارات")}>
            <div className="app-page d-flex flex-column gap-3">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title={t("ai.escalations.title", undefined, "الاستفسارات")}
                        description={t("ai.escalations.index_description", undefined, "الأسئلة المرسلة إلى فريق بيكون وحالتها.")}
                    />
                    {can_escalate ? (
                        <Link href="/ai/pages/assistant" className="btn btn-primary">
                            <i className="bi bi-stars me-2" aria-hidden="true" />
                            {t("ai.assistant.ask", undefined, "اسأل المساعد")}
                        </Link>
                    ) : null}
                </div>
                <FilterChips
                    label={t("ai.escalations.filter_status", undefined, "تصفية حسب الحالة")}
                    current={filters.status ?? null}
                    options={[
                        { value: null, label: t("common.all", undefined, "الكل") },
                        { value: "open", label: t("ai.escalations.status.open", undefined, "مفتوحة") },
                    ]}
                    hrefFor={(v) => withQuery("/ai/pages/escalations", { status: v })}
                />
                <SectionCard title={t("ai.escalations.list", undefined, "قائمة الاستفسارات")}>
                    <DataTable
                        caption={t("ai.escalations.title", undefined, "الاستفسارات")}
                        columns={columns}
                        rowKey={(e) => e.id}
                        emptyTitle={
                            filters.status
                                ? t("ai.escalations.empty_filtered", undefined, "لا توجد استفسارات بهذه الحالة")
                                : t("ai.escalations.empty", undefined, "لا توجد استفسارات بعد")
                        }
                        emptyDescription={t("ai.escalations.empty_description", undefined, "عندما ترسل سؤالاً لفريق بيكون سيظهر هنا.")}
                        server={{ paginator: escalations, only: ["escalations"], query: { status: filters.status ?? undefined } }}
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
