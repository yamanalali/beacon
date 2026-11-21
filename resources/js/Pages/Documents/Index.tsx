import { Link, router } from "@inertiajs/react";
import { FormEvent, useMemo, useState } from "react";
import { DataTable, type Column, type Paginator } from "../../Components/Data/DataTable";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentUploader } from "../../Components/Documents/DocumentUploader";
import { ReceiptCapture } from "../../Components/Documents/ReceiptCapture";
import { archiveDocument } from "../../Components/Documents/archiveDocument";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import type { DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

function useDocumentColumns(canExtract: boolean, t: ReturnType<typeof useT>): Column<DocumentRow>[] {
    return useMemo(
        (): Column<DocumentRow>[] => [
            {
                key: "title",
                header: t("documents.column.document", undefined, "المستند"),
                primary: true,
                cell: (doc) => (
                    <a href={`/documents/${doc.public_id}`} className="fw-semibold">
                        <i className={`bi ${doc.is_image ? "bi-file-earmark-image" : "bi-file-earmark-pdf"} me-2`} aria-hidden="true" />
                        {doc.title}
                    </a>
                ),
            },
            {
                key: "uploaded_at",
                header: t("documents.column.uploaded_at", undefined, "تاريخ الرفع"),
                cell: (doc) => <bdi dir="ltr">{doc.uploaded_at ?? "—"}</bdi>,
                numeric: true,
            },
            {
                key: "size_kb",
                header: t("documents.column.size_kb", undefined, "الحجم (ك.ب)"),
                cell: (doc) => doc.size_kb,
                numeric: true,
                hideOnMobile: true,
            },
            {
                key: "uploader_name",
                header: t("documents.column.uploaded_by", undefined, "رفعه"),
                cell: (doc) => doc.uploader_name ?? "—",
                hideOnMobile: true,
            },
            {
                key: "transaction",
                header: t("documents.column.transaction", undefined, "العملية"),
                cell: (doc) =>
                    doc.transaction_public_id ? (
                        <a href={`/transactions/${doc.transaction_public_id}`}>
                            {t("documents.linked_transaction", undefined, "مرتبط بعملية")}
                        </a>
                    ) : (
                        "—"
                    ),
            },
            {
                key: "actions",
                header: "",
                hideable: false,
                cell: (doc) => (
                    <div className="d-flex flex-wrap gap-1">
                        {doc.is_previewable ? (
                            <a
                                className="btn btn-outline-primary btn-sm"
                                style={{ minHeight: "40px" }}
                                href={`/documents/${doc.public_id}/preview`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t("common.view", undefined, "عرض")}
                            </a>
                        ) : null}
                        {canExtract && !doc.transaction_public_id ? (
                            <button
                                type="button"
                                className="btn btn-primary btn-sm"
                                style={{ minHeight: "40px" }}
                                onClick={() => router.post(`/documents/${doc.public_id}/extractions`)}
                            >
                                {t("documents.record_as_transaction", undefined, "تسجيل كعملية")}
                            </button>
                        ) : null}
                        <a className="btn btn-outline-secondary btn-sm" style={{ minHeight: "40px" }} href={`/documents/${doc.public_id}/download`}>
                            {t("common.download", undefined, "تنزيل")}
                        </a>
                        {doc.can_archive ? (
                            <button
                                type="button"
                                className="btn btn-outline-danger btn-sm"
                                style={{ minHeight: "40px" }}
                                onClick={() =>
                                    archiveDocument(doc.public_id, {
                                        confirm: t(
                                            "documents.archive_confirm_short",
                                            undefined,
                                            "إخفاء هذا المستند من القائمة؟ الملف يبقى محفوظاً على الخادم.",
                                        ),
                                        reasonPrompt: t("documents.archive_reason_prompt", undefined, "سبب الإخفاء"),
                                        reasonFallback: t("documents.archive_reason_default", undefined, "إخفاء من القائمة"),
                                    })
                                }
                            >
                                {t("documents.hide_button", undefined, "إخفاء")}
                            </button>
                        ) : null}
                    </div>
                ),
            },
        ],
        [canExtract, t],
    );
}

export default function Index({
    enterprise,
    documents,
    filters = { search: "" },
    can_upload,
    can_extract,
}: {
    enterprise: EnterpriseRef;
    documents: Paginator<DocumentRow>;
    filters?: { search: string };
    can_upload: boolean;
    can_extract: boolean;
}) {
    const t = useT();
    const columns = useDocumentColumns(can_extract, t);
    const pageTitle = t("documents.title", undefined, "المستندات");
    const [search, setSearch] = useState(filters.search ?? "");

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            `/enterprises/${enterprise.public_id}/documents`,
            { search: search.trim() || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout title={pageTitle}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={pageTitle}
                    description={t(
                        "documents.index_description",
                        { name: enterprise.business_name },
                        `${enterprise.business_name} — الإيصالات والفواتير والوثائق الخاصة بالمنشأة.`,
                    )}
                />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}`} className="btn btn-link px-0">
                        {t("documents.back_to_profile", undefined, "العودة إلى ملف المنشأة")}
                    </Link>
                </div>
                {can_upload && can_extract ? (
                    <SectionCard title={t("documents.record_from_receipt", undefined, "تسجيل عملية من إيصال")}>
                        <ReceiptCapture enterpriseId={enterprise.public_id} currentIds={documents.data.map((d) => d.public_id)} />
                    </SectionCard>
                ) : null}
                <div className="row g-3">
                    {can_upload ? (
                        <div className="col-12 col-lg-4">
                            <SectionCard title={t("documents.upload", undefined, "رفع مستند")}>
                                <DocumentUploader enterpriseId={enterprise.public_id} />
                            </SectionCard>
                        </div>
                    ) : null}
                    <div className={can_upload ? "col-12 col-lg-8" : "col-12"}>
                        <SectionCard title={t("documents.all_title", undefined, "كل المستندات")}>
                            <form className="mb-3" onSubmit={submitSearch} role="search">
                                <div className="d-flex flex-column flex-sm-row gap-2 align-items-stretch">
                                    <div className="flex-grow-1">
                                        <TextInput
                                            id="documents-search"
                                            name="search"
                                            label={t("documents.search_label", undefined, "بحث في المستندات")}
                                            value={search}
                                            onChange={setSearch}
                                            placeholder={t("documents.search_placeholder", undefined, "عنوان المستند أو اسم الملف")}
                                        />
                                    </div>
                                    <div className="d-flex gap-2 align-items-end pb-3 pb-sm-0">
                                        <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }}>
                                            <i className="bi bi-search me-1" aria-hidden="true" />
                                            {t("common.search", undefined, "بحث")}
                                        </button>
                                        {filters.search ? (
                                            <button
                                                type="button"
                                                className="btn btn-outline-secondary"
                                                style={{ minHeight: "48px" }}
                                                onClick={() => {
                                                    setSearch("");
                                                    router.get(`/enterprises/${enterprise.public_id}/documents`, {}, { preserveState: true, replace: true });
                                                }}
                                            >
                                                {t("common.reset", undefined, "إعادة تعيين")}
                                            </button>
                                        ) : null}
                                    </div>
                                </div>
                            </form>
                            <DataTable
                                caption={t("documents.all_title", undefined, "كل المستندات")}
                                columns={columns}
                                rowKey={(doc) => doc.public_id}
                                emptyTitle={
                                    filters.search
                                        ? t("documents.empty_search", undefined, "لا نتائج لهذا البحث")
                                        : t("documents.empty_list", undefined, "لا توجد مستندات بعد")
                                }
                                server={{ paginator: documents, only: ["documents", "filters"] }}
                            />
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
