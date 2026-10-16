import { Link, router } from "@inertiajs/react";
import { DataTable, type Column, type Paginator } from "../../Components/Data/DataTable";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentUploader } from "../../Components/Documents/DocumentUploader";
import { ReceiptCapture } from "../../Components/Documents/ReceiptCapture";
import type { DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

const columns = (canExtract: boolean): Column<DocumentRow>[] => [
    {
        key: "title",
        header: "المستند",
        primary: true,
        cell: (doc) => (
            <a href={`/documents/${doc.public_id}`} className="fw-semibold">
                <i className={`bi ${doc.is_image ? "bi-file-earmark-image" : "bi-file-earmark-pdf"} me-2`} aria-hidden="true" />
                {doc.title}
            </a>
        ),
    },
    { key: "uploaded_at", header: "تاريخ الرفع", cell: (doc) => <bdi dir="ltr">{doc.uploaded_at ?? "—"}</bdi>, numeric: true },
    { key: "size_kb", header: "الحجم (ك.ب)", cell: (doc) => doc.size_kb, numeric: true, hideOnMobile: true },
    { key: "uploader_name", header: "رفعه", cell: (doc) => doc.uploader_name ?? "—", hideOnMobile: true },
    {
        key: "transaction",
        header: "العملية",
        cell: (doc) => (doc.transaction_public_id ? <a href={`/transactions/${doc.transaction_public_id}`}>مرتبط بعملية</a> : "—"),
    },
    {
        key: "actions",
        header: "",
        hideable: false,
        cell: (doc) => (
            <div className="d-flex flex-wrap gap-1">
                {doc.is_previewable ? (
                    <a className="btn btn-outline-primary btn-sm" style={{ minHeight: "40px" }} href={`/documents/${doc.public_id}/preview`} target="_blank" rel="noopener noreferrer">
                        عرض
                    </a>
                ) : null}
                {canExtract && !doc.transaction_public_id ? (
                    <button type="button" className="btn btn-primary btn-sm" style={{ minHeight: "40px" }} onClick={() => router.post(`/documents/${doc.public_id}/extractions`)}>
                        تسجيل كعملية
                    </button>
                ) : null}
                <a className="btn btn-outline-secondary btn-sm" style={{ minHeight: "40px" }} href={`/documents/${doc.public_id}/download`}>
                    تنزيل
                </a>
            </div>
        ),
    },
];

export default function Index({
    enterprise,
    documents,
    can_upload,
    can_extract,
}: {
    enterprise: EnterpriseRef;
    documents: Paginator<DocumentRow>;
    can_upload: boolean;
    can_extract: boolean;
}) {
    return (
        <AppLayout title="المستندات">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="المستندات" description={`${enterprise.business_name} — الإيصالات والفواتير والوثائق الخاصة بالمنشأة.`} />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}`} className="btn btn-link px-0">
                        العودة إلى ملف المنشأة
                    </Link>
                </div>
                {can_upload && can_extract ? (
                    <SectionCard title="تسجيل عملية من إيصال">
                        <ReceiptCapture enterpriseId={enterprise.public_id} currentIds={documents.data.map((d) => d.public_id)} />
                    </SectionCard>
                ) : null}
                <div className="row g-3">
                    {can_upload ? (
                        <div className="col-12 col-lg-4">
                            <SectionCard title="رفع مستند">
                                <DocumentUploader enterpriseId={enterprise.public_id} />
                            </SectionCard>
                        </div>
                    ) : null}
                    <div className={can_upload ? "col-12 col-lg-8" : "col-12"}>
                        <SectionCard title="كل المستندات">
                            <DataTable
                                caption="كل المستندات"
                                columns={columns(can_extract)}
                                rowKey={(doc) => doc.public_id}
                                emptyTitle="لا توجد مستندات بعد"
                                server={{ paginator: documents, only: ["documents"] }}
                            />
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
