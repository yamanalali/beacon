import { Link } from "@inertiajs/react";
import { useMemo } from "react";
import { DataTable, type Column, type Paginator } from "../../Components/Data/DataTable";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import type { EnterpriseRef } from "../../types/bookkeeping";
import { Money } from "../../Components/Common/Money";
import { useT } from "../../Hooks/useT";

export type PendingExtractionRow = {
    public_id: string;
    document_title: string | null;
    created_at: string | null;
    suggested_date: string | null;
    suggested_amount: string | null;
    suggested_currency: string | null;
    suggested_counterparty: string | null;
    needs_review: boolean;
    confidence: string | null;
};

function usePendingColumns(t: ReturnType<typeof useT>): Column<PendingExtractionRow>[] {
    return useMemo(
        () => [
            {
                key: "document_title",
                header: t("extractions.column.invoice", undefined, "الفاتورة"),
                primary: true,
                cell: (x) => (
                    <Link href={`/extractions/${x.public_id}`} className="fw-semibold">
                        {x.document_title ?? t("extractions.invoice_fallback", undefined, "فاتورة")}
                    </Link>
                ),
            },
            {
                key: "created_at",
                header: t("extractions.column.read_at", undefined, "تاريخ القراءة"),
                cell: (x) => <bdi dir="ltr">{x.created_at ?? "—"}</bdi>,
                numeric: true,
            },
            {
                key: "suggested_counterparty",
                header: t("extractions.column.suggested_counterparty", undefined, "الجهة المقترحة"),
                cell: (x) => x.suggested_counterparty ?? "—",
                hideOnMobile: true,
            },
            {
                key: "suggested_amount",
                header: t("extractions.column.suggested_amount", undefined, "المبلغ المقترح"),
                numeric: true,
                cell: (x) => (x.suggested_amount ? <Money amount={x.suggested_amount} currency={x.suggested_currency} /> : "—"),
            },
            {
                key: "needs_review",
                header: t("extractions.column.review", undefined, "التدقيق"),
                cell: (x) => (x.needs_review ? t("extractions.needs_review", undefined, "تحتاج تدقيقاً") : "—"),
                hideOnMobile: true,
            },
            {
                key: "actions",
                header: "",
                hideable: false,
                cell: (x) => (
                    <Link className="btn btn-primary btn-sm" style={{ minHeight: "40px" }} href={`/extractions/${x.public_id}`}>
                        {t("extractions.review", undefined, "مراجعة")}
                    </Link>
                ),
            },
        ],
        [t],
    );
}

export default function Pending({ enterprise, extractions }: { enterprise: EnterpriseRef; extractions: Paginator<PendingExtractionRow> }) {
    const t = useT();
    const columns = usePendingColumns(t);

    return (
        <AppLayout title={t("extractions.pending.title", undefined, "فواتير بانتظار المراجعة")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("extractions.pending.title", undefined, "فواتير بانتظار المراجعة")}
                    description={t(
                        "extractions.pending.description",
                        { name: enterprise.business_name },
                        `${enterprise.business_name} — اقتراحات قراءة الفواتير التي لم تؤكَّد أو ترفض بعد.`,
                    )}
                />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}/documents`} className="btn btn-link px-0">
                        {t("extractions.back_to_documents", undefined, "العودة إلى المستندات")}
                    </Link>
                </div>
                <SectionCard title={t("extractions.pending.section", undefined, "بانتظار المراجعة")}>
                    <DataTable
                        caption={t("extractions.pending.title", undefined, "فواتير بانتظار المراجعة")}
                        columns={columns}
                        rowKey={(x) => x.public_id}
                        emptyTitle={t("extractions.pending.empty", undefined, "لا توجد فواتير بانتظار المراجعة")}
                        server={{ paginator: extractions, only: ["extractions"] }}
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
