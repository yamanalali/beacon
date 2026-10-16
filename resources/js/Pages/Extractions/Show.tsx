import { Link, router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import type { BookkeepingOptions, DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

type Extraction = {
    public_id: string;
    status: "pending_review" | "confirmed" | "rejected";
    status_label: string;
    is_manual: boolean;
    needs_review: boolean;
    suggested_date: string | null;
    suggested_amount: string | null;
    suggested_currency: string | null;
    suggested_counterparty: string | null;
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
}: {
    enterprise: EnterpriseRef;
    extraction: Extraction;
    type: string;
    category_suggestion: { category: string; label: string; needs_review: boolean } | null;
    options: BookkeepingOptions;
    can_review: boolean;
}) {
    const form = useForm({
        type,
        transaction_date: extraction.suggested_date ?? new Date().toISOString().slice(0, 10),
        amount: extraction.suggested_amount ?? "",
        currency: extraction.suggested_currency ?? options.default_currency,
        category: category_suggestion?.category ?? "",
        payment_status: "paid",
        paid_amount: "",
        counterparty: extraction.suggested_counterparty ?? "",
        description: "",
    });
    const categories = options.categories[form.data.type] ?? [];

    return (
        <AppLayout title="مراجعة الإيصال">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="مراجعة الإيصال قبل الحفظ" description={enterprise.business_name} />
                <div>
                    <StatusBadge label={extraction.status_label} tone={extraction.status === "confirmed" ? "success" : extraction.status === "rejected" ? "neutral" : "warning"} />
                </div>
                {extraction.status === "pending_review" ? (
                    <div className="alert alert-warning mb-0" role="status">
                        {extraction.is_manual
                            ? "القراءة التلقائية للإيصالات غير مفعلة بعد. أدخل البيانات من الإيصال يدوياً ثم احفظ."
                            : "هذه القيم اقتراحات تلقائية وقد تحتوي أخطاء. راجعها وصححها قبل الحفظ."}
                    </div>
                ) : null}
                {extraction.confirmed_transaction_public_id ? (
                    <Link href={`/transactions/${extraction.confirmed_transaction_public_id}`} className="btn btn-outline-primary align-self-start">
                        عرض العملية المحفوظة
                    </Link>
                ) : null}

                <div className="row g-3">
                    <div className="col-12 col-lg-5">
                        <SectionCard title="المستند">
                            <DocumentList documents={[extraction.document]} />
                        </SectionCard>
                    </div>
                    {can_review ? (
                        <div className="col-12 col-lg-7">
                            <SectionCard title="بيانات العملية">
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.post(`/extractions/${extraction.public_id}/confirm`);
                                    }}
                                >
                                    <SelectInput id="type" label="نوع العملية" value={form.data.type} options={options.types} onChange={(v) => form.setData((d) => ({ ...d, type: v, category: "" }))} error={form.errors.type} />
                                    <TextInput id="transaction_date" name="transaction_date" type="date" label="التاريخ" value={form.data.transaction_date} onChange={(v) => form.setData("transaction_date", v)} error={form.errors.transaction_date} />
                                    <div className="row g-2">
                                        <div className="col-7">
                                            <TextInput id="amount" name="amount" label="المبلغ" value={form.data.amount} onChange={(v) => form.setData("amount", v)} error={form.errors.amount} />
                                        </div>
                                        <div className="col-5">
                                            <SelectInput id="currency" label="العملة" value={form.data.currency} options={options.currencies} onChange={(v) => form.setData("currency", v)} error={form.errors.currency} />
                                        </div>
                                    </div>
                                    <SelectInput id="category" label="التصنيف" value={form.data.category} options={categories} placeholder="اختر التصنيف" onChange={(v) => form.setData("category", v)} error={form.errors.category} />
                                    {category_suggestion ? (
                                        <p className="small text-body-secondary">
                                            <i className="bi bi-stars me-1" aria-hidden="true" />
                                            تصنيف مقترح تلقائياً: {category_suggestion.label}
                                            {category_suggestion.needs_review ? " (يحتاج مراجعة)" : ""} — يمكنك تغييره.
                                        </p>
                                    ) : null}
                                    <SelectInput id="payment_status" label="هل تم الدفع؟" value={form.data.payment_status} options={options.payment_statuses} onChange={(v) => form.setData("payment_status", v)} error={form.errors.payment_status} />
                                    {form.data.payment_status === "partially_paid" ? (
                                        <TextInput id="paid_amount" name="paid_amount" label="المبلغ المدفوع الآن" value={form.data.paid_amount} onChange={(v) => form.setData("paid_amount", v)} error={form.errors.paid_amount} />
                                    ) : null}
                                    <TextInput id="counterparty" name="counterparty" label="الزبون أو المورد (اختياري)" value={form.data.counterparty} onChange={(v) => form.setData("counterparty", v)} error={form.errors.counterparty} />
                                    <div className="d-flex flex-wrap gap-2">
                                        <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                                            تأكيد وحفظ العملية
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary"
                                            style={{ minHeight: "48px" }}
                                            onClick={() => {
                                                if (window.confirm("هل تريد تجاهل هذا الاقتراح؟ سيبقى المستند محفوظاً.")) {
                                                    router.post(`/extractions/${extraction.public_id}/reject`);
                                                }
                                            }}
                                        >
                                            تجاهل
                                        </button>
                                    </div>
                                </form>
                            </SectionCard>
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
