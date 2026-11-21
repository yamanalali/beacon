import { router } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import { EmptyState } from "../Feedback/EmptyState";
import type { DocumentRow } from "../../types/bookkeeping";
import { archiveDocument } from "./archiveDocument";

export function DocumentList({ documents, canExtract = false }: { documents: DocumentRow[]; canExtract?: boolean }) {
    const t = useT();
    if (documents.length === 0) {
        return <EmptyState title={t("documents.empty_list", undefined, "لا توجد مستندات بعد")} />;
    }

    return (
        <ul className="list-unstyled mb-0 d-flex flex-column gap-2">
            {documents.map((doc) => (
                <li key={doc.public_id} className="border rounded p-3 d-flex flex-wrap align-items-center gap-2">
                    <i className={`bi ${doc.is_image ? "bi-file-earmark-image" : "bi-file-earmark-pdf"} fs-4`} aria-hidden="true" />
                    <div className="flex-grow-1">
                        <a href={`/documents/${doc.public_id}`} className="mb-0 fw-semibold d-block">{doc.title}</a>
                        <p className="mb-0 small text-body-secondary">
                            {doc.uploaded_at} · {doc.size_kb} {t("documents.size_kb_unit", undefined, "ك.ب")}{doc.uploader_name ? ` · ${doc.uploader_name}` : ""}
                            {doc.transaction_public_id ? (
                                <>
                                    {" · "}
                                    <a href={`/transactions/${doc.transaction_public_id}`}>{t("documents.linked_transaction", undefined, "مرتبط بعملية")}</a>
                                </>
                            ) : null}
                        </p>
                    </div>
                    {doc.is_previewable ? (
                        <a className="btn btn-outline-primary" style={{ minHeight: "44px" }} href={`/documents/${doc.public_id}/preview`} target="_blank" rel="noopener noreferrer">
                            {t("common.view", undefined, "عرض")}
                        </a>
                    ) : null}
                    {canExtract && !doc.transaction_public_id ? (
                        <button
                            type="button"
                            className="btn btn-primary"
                            style={{ minHeight: "44px" }}
                            onClick={() => router.post(`/documents/${doc.public_id}/extractions`)}
                        >
                            {t("documents.record_as_transaction", undefined, "تسجيل كعملية")}
                        </button>
                    ) : null}
                    {doc.can_download !== false ? <a className="btn btn-outline-secondary" style={{ minHeight: "44px" }} href={`/documents/${doc.public_id}/download`}>
                        {t("common.download", undefined, "تنزيل")}
                    </a> : null}
                    {doc.can_archive ? (
                        <button
                            type="button"
                            className="btn btn-outline-danger"
                            style={{ minHeight: "44px" }}
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
                </li>
            ))}
        </ul>
    );
}
