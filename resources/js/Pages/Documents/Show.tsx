import { Link, useForm } from "@inertiajs/react";
import { useMemo } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import type { DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

type Detail = DocumentRow & { original_name: string; is_archived: boolean; archived_at: string | null; archive_reason: string | null };

function useDocumentActionLabels(t: ReturnType<typeof useT>): Record<string, string> {
    return useMemo(
        () => ({
            "document.uploaded": t("documents.history.uploaded", undefined, "رفع المستند"),
            "document.previewed": t("documents.history.previewed", undefined, "عرض المستند"),
            "document.downloaded": t("documents.history.downloaded", undefined, "تنزيل المستند"),
            "document.metadata_updated": t("documents.history.metadata_updated", undefined, "تعديل البيانات"),
            "document.archived": t("documents.history.archived", undefined, "أرشفة المستند"),
            "document.unarchived": t("documents.history.unarchived", undefined, "استعادة المستند من الأرشيف"),
        }),
        [t],
    );
}

export default function Show({
    enterprise,
    document,
    history,
    transactions,
    can_update,
    can_archive,
    can_unarchive = false,
}: {
    enterprise: EnterpriseRef;
    document: Detail;
    history: { id: number; action: string; created_at: string | null; actor_name: string | null }[];
    transactions: { value: string; label: string }[];
    can_update: boolean;
    can_archive: boolean;
    can_unarchive?: boolean;
}) {
    const t = useT();
    const actionLabels = useDocumentActionLabels(t);
    const meta = useForm({ title: document.title, transaction: document.transaction_public_id ?? "" });
    const archive = useForm({ reason: "" });
    const unarchive = useForm({});

    return (
        <AppLayout title={t("documents.show.title", undefined, "المستند")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={document.title} description={enterprise.business_name} />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}/documents`} className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                        {t("documents.all_title", undefined, "كل المستندات")}
                    </Link>
                </div>
                {document.is_archived ? (
                    <div className="alert alert-warning mb-0" role="status">
                        {t("documents.show.archived_notice", { date: document.archived_at ?? "", reason: document.archive_reason ?? "" }, `هذا المستند مؤرشف منذ ${document.archived_at}. السبب: ${document.archive_reason}`)}
                        {can_unarchive ? (
                            <button
                                type="button"
                                className="btn btn-primary d-block mt-2"
                                style={{ minHeight: "48px" }}
                                disabled={unarchive.processing}
                                onClick={() => unarchive.post(`/documents/${document.public_id}/unarchive`)}
                            >
                                {t("documents.show.unarchive_button", undefined, "استعادة المستند من الأرشيف")}
                            </button>
                        ) : null}
                    </div>
                ) : null}
                <div className="row g-3">
                    <div className="col-12 col-lg-6 d-flex flex-column gap-3">
                        <SectionCard title={t("documents.show.file_section", undefined, "الملف")}>
                            <DocumentList documents={[document]} />
                            <p className="small text-body-secondary mt-2 mb-0">
                                {t("documents.show.original_name", undefined, "الاسم الأصلي")}: {document.original_name}
                            </p>
                        </SectionCard>
                        {can_update ? (
                            <SectionCard title={t("documents.show.edit_metadata", undefined, "تعديل البيانات")}>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        meta.put(`/documents/${document.public_id}`, { preserveScroll: true });
                                    }}
                                >
                                    <TextInput
                                        id="title"
                                        name="title"
                                        label={t("documents.show.title_label", undefined, "العنوان")}
                                        value={meta.data.title}
                                        onChange={(v) => meta.setData("title", v)}
                                        error={meta.errors.title}
                                    />
                                    <SelectInput
                                        id="transaction"
                                        label={t("documents.show.link_transaction_optional", undefined, "ربط بعملية مالية (اختياري)")}
                                        value={meta.data.transaction}
                                        placeholder={t("documents.show.no_link", undefined, "بدون ربط")}
                                        options={transactions}
                                        onChange={(v) => meta.setData("transaction", v)}
                                        error={meta.errors.transaction}
                                    />
                                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={meta.processing}>
                                        {t("documents.show.save_metadata", undefined, "حفظ البيانات")}
                                    </button>
                                </form>
                            </SectionCard>
                        ) : null}
                        {can_archive ? (
                            <SectionCard title={t("documents.show.archive_section", undefined, "إخفاء / أرشفة المستند")}>
                                <p className="small">
                                    {t(
                                        "documents.show.archive_hint",
                                        undefined,
                                        "سيختفي المستند من القوائم بعد الإخفاء، لكن الملف يبقى محفوظاً على الخادم ولا يُحذف نهائياً.",
                                    )}
                                </p>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        if (window.confirm(t("documents.show.archive_confirm", undefined, "هل تريد إخفاء هذا المستند من القائمة؟"))) {
                                            archive.post(`/documents/${document.public_id}/archive`);
                                        }
                                    }}
                                >
                                    <TextInput
                                        id="reason"
                                        name="reason"
                                        label={t("documents.show.archive_reason", undefined, "سبب الإخفاء")}
                                        value={archive.data.reason}
                                        onChange={(v) => archive.setData("reason", v)}
                                        error={archive.errors.reason}
                                    />
                                    <button type="submit" className="btn btn-outline-danger" style={{ minHeight: "48px" }} disabled={archive.processing}>
                                        {t("documents.show.archive_button", undefined, "إخفاء المستند")}
                                    </button>
                                </form>
                            </SectionCard>
                        ) : null}
                    </div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title={t("documents.show.history_section", undefined, "السجل")}>
                            {history.length === 0 ? (
                                <EmptyState title={t("documents.show.no_history", undefined, "لا يوجد سجل")} />
                            ) : (
                                <ul className="list-unstyled mb-0">
                                    {history.map((h) => (
                                        <li key={h.id} className="border-bottom py-2 d-flex justify-content-between gap-2">
                                            <span>
                                                {actionLabels[h.action] ?? h.action}
                                                {h.actor_name ? ` · ${h.actor_name}` : ""}
                                            </span>
                                            <span dir="ltr" className="small text-body-secondary">
                                                {h.created_at}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
