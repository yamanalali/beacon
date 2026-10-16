import { Link, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import type { DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

type Detail = DocumentRow & { original_name: string; is_archived: boolean; archived_at: string | null; archive_reason: string | null };

const actionLabels: Record<string, string> = {
    "document.uploaded": "رفع المستند",
    "document.previewed": "عرض المستند",
    "document.downloaded": "تنزيل المستند",
    "document.metadata_updated": "تعديل البيانات",
    "document.archived": "أرشفة المستند",
};

export default function Show({
    enterprise,
    document,
    history,
    transactions,
    can_update,
    can_archive,
}: {
    enterprise: EnterpriseRef;
    document: Detail;
    history: { id: number; action: string; created_at: string | null; actor_name: string | null }[];
    transactions: { value: string; label: string }[];
    can_update: boolean;
    can_archive: boolean;
}) {
    const meta = useForm({ title: document.title, transaction: document.transaction_public_id ?? "" });
    const archive = useForm({ reason: "" });

    return (
        <AppLayout title="المستند">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={document.title} description={enterprise.business_name} />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}/documents`} className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                        كل المستندات
                    </Link>
                </div>
                {document.is_archived ? (
                    <div className="alert alert-warning mb-0" role="status">
                        هذا المستند مؤرشف منذ {document.archived_at}. السبب: {document.archive_reason}
                    </div>
                ) : null}
                <div className="row g-3">
                    <div className="col-12 col-lg-6 d-flex flex-column gap-3">
                        <SectionCard title="الملف">
                            <DocumentList documents={[document]} />
                            <p className="small text-body-secondary mt-2 mb-0">الاسم الأصلي: {document.original_name}</p>
                        </SectionCard>
                        {can_update ? (
                            <SectionCard title="تعديل البيانات">
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        meta.put(`/documents/${document.public_id}`, { preserveScroll: true });
                                    }}
                                >
                                    <TextInput id="title" name="title" label="العنوان" value={meta.data.title} onChange={(v) => meta.setData("title", v)} error={meta.errors.title} />
                                    <SelectInput id="transaction" label="ربط بعملية مالية (اختياري)" value={meta.data.transaction} placeholder="بدون ربط" options={transactions} onChange={(v) => meta.setData("transaction", v)} error={meta.errors.transaction} />
                                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={meta.processing}>
                                        حفظ البيانات
                                    </button>
                                </form>
                            </SectionCard>
                        ) : null}
                        {can_archive ? (
                            <SectionCard title="أرشفة المستند">
                                <p className="small">سيختفي المستند من القوائم، لكن الملف يبقى محفوظاً ولا يُحذف.</p>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        if (window.confirm("هل تريد أرشفة هذا المستند؟")) {
                                            archive.post(`/documents/${document.public_id}/archive`);
                                        }
                                    }}
                                >
                                    <TextInput id="reason" name="reason" label="سبب الأرشفة" value={archive.data.reason} onChange={(v) => archive.setData("reason", v)} error={archive.errors.reason} />
                                    <button type="submit" className="btn btn-outline-danger" style={{ minHeight: "48px" }} disabled={archive.processing}>
                                        أرشفة المستند
                                    </button>
                                </form>
                            </SectionCard>
                        ) : null}
                    </div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title="السجل">
                            {history.length === 0 ? (
                                <EmptyState title="لا يوجد سجل" />
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
