import { transactionToday } from "../../lib/transactionInput";
import type { ReactNode } from "react";
import { Link, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { DocumentUploader } from "../../Components/Documents/DocumentUploader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { PaymentStatusBadge } from "../../Components/Financial/PaymentStatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";
import type { DocumentRow, EnterpriseRef, TransactionItemsProps, TransactionRow } from "../../types/bookkeeping";
import { ItemsTable } from "../../Components/Transactions/ItemsEditor";
import { Money } from "../../Components/Common/Money";

type Detail = TransactionRow & {
    creator_name: string | null;
    correction_reason: string | null;
    created_at: string | null;
    corrects_public_id: string | null;
    corrected_by_public_id: string | null;
} & Partial<TransactionItemsProps>;

type Settlement = { id: number; settlement_date: string | null; amount: string; note: string | null; creator_name: string | null };

function Line({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="d-flex justify-content-between gap-3 border-bottom py-2">
            <span className="text-body-secondary">{label}</span>
            <span className="fw-semibold text-end">{value === null || value === "" ? "—" : value}</span>
        </div>
    );
}

export default function Show({
    enterprise,
    transaction,
    settlements,
    documents,
    can_create = false,
    can_correct,
    can_approve = false,
    can_settle,
    can_upload,
}: {
    enterprise: EnterpriseRef;
    transaction: Detail;
    settlements: Settlement[];
    documents: DocumentRow[];
    can_create?: boolean;
    can_correct: boolean;
    can_approve?: boolean;
    can_settle: boolean;
    can_upload: boolean;
}) {
    const form = useForm({ settlement_date: transactionToday(), amount: transaction.outstanding, note: "" });
    const approval = useForm({});
    const settleLabel = transaction.is_inflow ? "تسجيل دفعة مستلمة" : "تسجيل دفعة مدفوعة";

    return (
        <AppLayout title="تفاصيل العملية">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={`${transaction.type_label} · ${transaction.category_label}`} description={enterprise.business_name} />
                <div className="d-flex flex-wrap gap-2">
                    <Link href={`/enterprises/${enterprise.public_id}/transactions`} className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                        كل المعاملات
                    </Link>
                    {can_create && transaction.status === "posted" ? <Link href={`/enterprises/${enterprise.public_id}/transactions/create?copy_from=${transaction.public_id}`} className="btn btn-outline-primary" style={{ minHeight: "48px" }}>تسجيل عملية مشابهة</Link> : null}
                    {can_correct ? (
                        <Link href={`/transactions/${transaction.public_id}/correct`} className="btn btn-outline-primary" style={{ minHeight: "48px" }}>
                            <i className="bi bi-pencil me-2" aria-hidden="true" />
                            تصحيح العملية
                        </Link>
                    ) : null}
                </div>

                {transaction.status === "pending_approval" ? <div className="alert alert-warning mb-0" role="status">هذه العملية بانتظار الموافقة ولا تدخل التقارير المالية.{can_approve ? <button type="button" className="btn btn-primary d-block mt-2" disabled={approval.processing} onClick={() => approval.post(`/transactions/${transaction.public_id}/approve`)}>اعتماد العملية</button> : null}</div> : null}
                {transaction.is_superseded ? (
                    <div className="alert alert-warning mb-0" role="status">
                        تم تصحيح هذا السجل ولم يعد يُحتسب.{" "}
                        {transaction.corrected_by_public_id ? <Link href={`/transactions/${transaction.corrected_by_public_id}`}>عرض السجل المصحح</Link> : null}
                    </div>
                ) : null}

                <div className="row g-3">
                    <div className="col-12 col-lg-6 d-flex flex-column gap-3">
                        <SectionCard title="البيانات">
                            <Line label="التاريخ" value={transaction.transaction_date} />
                            <Line label="المبلغ" value={<Money amount={transaction.amount} currency={transaction.currency} />} />
                            <Line label="المتبقي" value={<Money amount={transaction.outstanding} currency={transaction.currency} />} />
                            <Line label="حالة الدفع" value={<PaymentStatusBadge status={transaction.payment_status} label={transaction.payment_status_label} />} />
                            <Line label="الحالة" value={<StatusBadge label={transaction.status_label} tone={transaction.is_superseded ? "neutral" : "success"} />} />
                            <Line label="الزبون أو المورد" value={transaction.counterparty} />
                            <Line label="ملاحظات" value={transaction.description} />
                            <Line label="سجّلها" value={transaction.creator_name} />
                            {transaction.corrects_public_id ? (
                                <>
                                    <Line label="سبب التصحيح" value={transaction.correction_reason} />
                                    <Line label="السجل الأصلي" value={<Link href={`/transactions/${transaction.corrects_public_id}`}>عرض</Link>} />
                                </>
                            ) : null}
                        </SectionCard>
                        <SectionCard title="البنود">
                            <ItemsTable
                                items={transaction.items ?? []}
                                itemsTotal={transaction.items_total ?? null}
                                mismatch={transaction.items_mismatch === true}
                                amount={transaction.amount}
                                currency={transaction.currency}
                            />
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6 d-flex flex-column gap-3">
                        <SectionCard title="الدفعات">
                            {settlements.length === 0 ? (
                                <EmptyState title="لا توجد دفعات بعد" />
                            ) : (
                                <ul className="list-unstyled mb-0">
                                    {settlements.map((s) => (
                                        <li key={s.id} className="border-bottom py-2 d-flex justify-content-between gap-2">
                                            <span>
                                                {s.settlement_date}
                                                {s.note ? ` · ${s.note}` : ""}
                                            </span>
                                            <Money amount={s.amount} currency={transaction.currency} className="fw-semibold" />
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {can_settle ? (
                                <form
                                    className="mt-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.post(`/transactions/${transaction.public_id}/settlements`, { preserveScroll: true });
                                    }}
                                >
                                    <h3 className="h6">{settleLabel}</h3>
                                    <TextInput id="settlement_date" name="settlement_date" type="date" label="تاريخ الدفعة" value={form.data.settlement_date} onChange={(v) => form.setData("settlement_date", v)} error={form.errors.settlement_date} />
                                    <TextInput id="settlement_amount" name="amount" label="المبلغ" value={form.data.amount} onChange={(v) => form.setData("amount", v)} error={form.errors.amount} />
                                    <TextInput id="settlement_note" name="note" label="ملاحظة (اختياري)" value={form.data.note} onChange={(v) => form.setData("note", v)} error={form.errors.note} />
                                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                                        {settleLabel}
                                    </button>
                                </form>
                            ) : null}
                        </SectionCard>
                        <SectionCard title="الإيصالات والفواتير">
                            <DocumentList documents={documents} />
                            {can_upload ? (
                                <div className="mt-3">
                                    <DocumentUploader enterpriseId={enterprise.public_id} transactionId={transaction.public_id} />
                                </div>
                            ) : null}
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
