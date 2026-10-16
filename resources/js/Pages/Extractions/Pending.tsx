import { Link } from "@inertiajs/react";
import { DataTable, type Column, type Paginator } from "../../Components/Data/DataTable";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import type { EnterpriseRef } from "../../types/bookkeeping";

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

const columns: Column<PendingExtractionRow>[] = [
    {
        key: "document_title",
        header: "الفاتورة",
        primary: true,
        cell: (x) => (
            <Link href={`/extractions/${x.public_id}`} className="fw-semibold">
                {x.document_title ?? "فاتورة"}
            </Link>
        ),
    },
    { key: "created_at", header: "تاريخ القراءة", cell: (x) => <bdi dir="ltr">{x.created_at ?? "—"}</bdi>, numeric: true },
    { key: "suggested_counterparty", header: "الجهة المقترحة", cell: (x) => x.suggested_counterparty ?? "—", hideOnMobile: true },
    {
        key: "suggested_amount",
        header: "المبلغ المقترح",
        numeric: true,
        cell: (x) => (x.suggested_amount ? <bdi dir="ltr">{`${x.suggested_amount} ${x.suggested_currency ?? ""}`.trim()}</bdi> : "—"),
    },
    { key: "needs_review", header: "التدقيق", cell: (x) => (x.needs_review ? "تحتاج تدقيقاً" : "—"), hideOnMobile: true },
    {
        key: "actions",
        header: "",
        hideable: false,
        cell: (x) => (
            <Link className="btn btn-primary btn-sm" style={{ minHeight: "40px" }} href={`/extractions/${x.public_id}`}>
                مراجعة
            </Link>
        ),
    },
];

export default function Pending({ enterprise, extractions }: { enterprise: EnterpriseRef; extractions: Paginator<PendingExtractionRow> }) {
    return (
        <AppLayout title="فواتير بانتظار المراجعة">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="فواتير بانتظار المراجعة" description={`${enterprise.business_name} — اقتراحات قراءة الفواتير التي لم تؤكَّد أو ترفض بعد.`} />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}/documents`} className="btn btn-link px-0">
                        العودة إلى المستندات
                    </Link>
                </div>
                <SectionCard title="بانتظار المراجعة">
                    <DataTable
                        caption="فواتير بانتظار المراجعة"
                        columns={columns}
                        rowKey={(x) => x.public_id}
                        emptyTitle="لا توجد فواتير بانتظار المراجعة"
                        server={{ paginator: extractions, only: ["extractions"] }}
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
