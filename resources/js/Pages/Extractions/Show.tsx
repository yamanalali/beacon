import { Link, router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { ReviewForm, initialReviewData, type CategorySuggestion, type ReviewFormData, type TransactionDraft } from "../../Components/Transactions/ReviewForm";
import type { BookkeepingOptions, DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

type Extraction = TransactionDraft & {
    public_id: string;
    status: "pending_review" | "confirmed" | "rejected";
    status_label: string;
    is_manual: boolean;
    document: DocumentRow;
    confirmed_transaction_public_id: string | null;
};

export default function Show({
    enterprise,
    extraction,
    type,
    category_suggestion,
    options,
    can_review,
    transaction_type_options,
    suggested_transaction_type = null,
}: {
    enterprise: EnterpriseRef;
    extraction: Extraction;
    type: string;
    category_suggestion: CategorySuggestion;
    options: BookkeepingOptions;
    can_review: boolean;
    /** Optional until the backend sends it. */
    transaction_type_options?: { value: string; label: string }[];
    suggested_transaction_type?: string | null;
}) {
    const draft: TransactionDraft = { ...extraction, suggested_transaction_type };
    const form = useForm<ReviewFormData>(initialReviewData(draft, type, options, category_suggestion));
    const doc = extraction.document;
    const pending = extraction.status === "pending_review";

    return (
        <AppLayout title="مراجعة الإيصال">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="راجع العملية قبل الحفظ" description={enterprise.business_name} />
                <div className="d-flex flex-wrap align-items-center gap-2">
                    <StatusBadge label={extraction.status_label} tone={extraction.status === "confirmed" ? "success" : extraction.status === "rejected" ? "neutral" : "warning"} />
                    {extraction.confirmed_transaction_public_id ? (
                        <Link href={`/transactions/${extraction.confirmed_transaction_public_id}`} className="btn btn-outline-primary btn-sm">
                            عرض العملية المحفوظة
                        </Link>
                    ) : null}
                </div>
                {pending && !can_review ? (
                    <div className="alert alert-info mb-0" role="status">يمكنك الاطلاع على الإيصال فقط؛ التأكيد متاح لمن يملك صلاحية تسجيل العمليات.</div>
                ) : null}

                <div className="row g-3">
                    {pending && can_review ? (
                        <div className="col-12 col-lg-7 order-2 order-lg-1">
                            <SectionCard title="بيانات العملية">
                                <ReviewForm
                                    draft={draft}
                                    data={form.data}
                                    setData={(patch) => form.setData((d) => ({ ...d, ...patch }))}
                                    options={options}
                                    suggestion={category_suggestion}
                                    typeOptions={transaction_type_options}
                                    errors={form.errors}
                                    processing={form.processing}
                                    onSubmit={() => form.post(`/extractions/${extraction.public_id}/confirm`)}
                                    onReject={() => {
                                        if (window.confirm("هل تريد تجاهل هذا الاقتراح؟ سيبقى المستند محفوظاً.")) {
                                            router.post(`/extractions/${extraction.public_id}/reject`);
                                        }
                                    }}
                                />
                            </SectionCard>
                        </div>
                    ) : null}
                    <div className={`col-12 ${pending && can_review ? "col-lg-5 order-1 order-lg-2" : ""}`}>
                        <SectionCard title="الإيصال">
                            {doc.is_image && doc.is_previewable ? (
                                <a href={`/documents/${doc.public_id}/preview`} target="_blank" rel="noopener noreferrer" className="d-block mb-3">
                                    <img src={`/documents/${doc.public_id}/preview`} alt={`صورة ${doc.title}`} loading="lazy"
                                        style={{ width: "100%", maxHeight: 320, objectFit: "contain", borderRadius: 12, background: "var(--app-background)" }} />
                                </a>
                            ) : null}
                            <DocumentList documents={[doc]} />
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
