import { transactionToday } from "../../lib/transactionInput";
import { Link, useForm } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import AppLayout from "../../Layouts/AppLayout";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { DocumentUploader } from "../../Components/Documents/DocumentUploader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { PaymentStatusBadge } from "../../Components/Financial/PaymentStatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";
import type { DocumentRow, EnterpriseRef, TransactionItemsProps, TransactionRow } from "../../types/bookkeeping";
import { Money } from "../../Components/Common/Money";
import { MismatchNote } from "../../Components/Transactions/ItemsEditor";

type Detail = TransactionRow & {
    creator_name: string | null;
    correction_reason: string | null;
    created_at: string | null;
    corrects_public_id: string | null;
    corrected_by_public_id: string | null;
} & Partial<TransactionItemsProps>;

type Settlement = { id: number; settlement_date: string | null; amount: string; note: string | null; creator_name: string | null };

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
    invoice_pdf_url = "#",
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
    invoice_pdf_url?: string;
    has_signature?: boolean;
}) {
    const t = useT();
    const form = useForm({ settlement_date: transactionToday(), amount: transaction.outstanding, note: "" });
    const approval = useForm({});
    const settleLabel = transaction.is_inflow
        ? t("transactions.settle.received", undefined, "تسجيل دفعة مستلمة")
        : t("transactions.settle.paid", undefined, "تسجيل دفعة مدفوعة");
    const items = transaction.items ?? [];

    return (
        <AppLayout title={t("transactions.invoice.title", undefined, "فاتورة")}>
            <div className="app-page d-flex flex-column gap-3">
                <div className="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div className="d-flex flex-wrap gap-2">
                        <Link href={`/enterprises/${enterprise.public_id}/transactions`} className="btn btn-outline-secondary" style={{ minHeight: 48 }}>
                            {t("transactions.all", undefined, "كل المعاملات")}
                        </Link>
                        {can_create && transaction.status === "posted" ? (
                            <Link
                                href={`/enterprises/${enterprise.public_id}/transactions/create?copy_from=${transaction.public_id}`}
                                className="btn btn-outline-primary"
                                style={{ minHeight: 48 }}
                            >
                                {t("transactions.copy_similar", undefined, "تسجيل عملية مشابهة")}
                            </Link>
                        ) : null}
                        {can_correct ? (
                            <Link href={`/transactions/${transaction.public_id}/correct`} className="btn btn-outline-primary" style={{ minHeight: 48 }}>
                                {t("transactions.correct", undefined, "تصحيح العملية")}
                            </Link>
                        ) : null}
                    </div>
                    <a href={invoice_pdf_url} className="btn btn-primary" style={{ minHeight: 48 }}>
                        <i className="bi bi-file-earmark-pdf me-2" aria-hidden="true" />
                        {t("transactions.invoice.download_pdf", undefined, "تنزيل PDF")}
                    </a>
                </div>

                {transaction.status === "pending_approval" ? (
                    <div className="alert alert-warning mb-0" role="status">
                        {t("transactions.pending_approval_notice", undefined, "هذه العملية بانتظار الموافقة ولا تدخل التقارير المالية.")}
                        {can_approve ? (
                            <button
                                type="button"
                                className="btn btn-primary d-block mt-2"
                                disabled={approval.processing}
                                onClick={() => approval.post(`/transactions/${transaction.public_id}/approve`)}
                            >
                                {t("transactions.approve", undefined, "اعتماد العملية")}
                            </button>
                        ) : null}
                    </div>
                ) : null}
                {transaction.is_superseded ? (
                    <div className="alert alert-warning mb-0" role="status">
                        {t("transactions.superseded_notice", undefined, "تم تصحيح هذا السجل ولم يعد يُحتسب.")}{" "}
                        {transaction.corrected_by_public_id ? (
                            <Link href={`/transactions/${transaction.corrected_by_public_id}`}>
                                {t("transactions.view_corrected_record", undefined, "عرض السجل المصحح")}
                            </Link>
                        ) : null}
                    </div>
                ) : null}

                <article className="invoice-sheet">
                    <header className="invoice-sheet__header">
                        <div>
                            <p className="invoice-sheet__eyebrow">{t("transactions.invoice.title", undefined, "فاتورة")}</p>
                            <h1 className="invoice-sheet__business">{enterprise.business_name}</h1>
                            <p className="invoice-sheet__meta mb-0">
                                {transaction.type_label} · {transaction.category_label}
                            </p>
                        </div>
                        <div className="invoice-sheet__ids">
                            <div>
                                <span>{t("transactions.invoice.number", undefined, "الرقم")}</span>
                                <strong><bdi dir="ltr">{transaction.public_id}</bdi></strong>
                            </div>
                            <div>
                                <span>{t("common.date", undefined, "التاريخ")}</span>
                                <strong>{transaction.transaction_date}</strong>
                            </div>
                            <div>
                                <span>{t("common.status", undefined, "الحالة")}</span>
                                <StatusBadge label={transaction.status_label} tone={transaction.is_superseded ? "neutral" : "success"} />
                            </div>
                        </div>
                    </header>

                    <section className="invoice-sheet__party">
                        <p className="invoice-sheet__label">{t("transactions.counterparty", undefined, "الزبون أو المورد")}</p>
                        <p className="invoice-sheet__party-name">{transaction.counterparty || "—"}</p>
                        {transaction.description ? <p className="invoice-sheet__note">{transaction.description}</p> : null}
                    </section>

                    <div className="table-responsive">
                        <table className="invoice-sheet__table" data-testid="items-table">
                            <thead>
                                <tr>
                                    <th>{t("transactions.invoice.description", undefined, "الوصف")}</th>
                                    <th>{t("transactions.invoice.qty", undefined, "الكمية")}</th>
                                    <th>{t("transactions.invoice.unit", undefined, "السعر")}</th>
                                    <th>{t("transactions.invoice.line_total", undefined, "الإجمالي")}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length > 0 ? (
                                    items.map((item, index) => (
                                        <tr key={`${item.description}-${index}`}>
                                            <td>{item.description}</td>
                                            <td>{item.quantity}</td>
                                            <td>
                                                <Money amount={String(item.unit_price)} currency={transaction.currency} />
                                            </td>
                                            <td>
                                                <Money amount={String(item.total)} currency={transaction.currency} />
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={3}>{transaction.category_label || t("transactions.items.empty", undefined, "لا توجد بنود لهذه العملية")}</td>
                                        <td>
                                            <Money amount={transaction.amount} currency={transaction.currency} />
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                        {transaction.items_mismatch ? (
                            <MismatchNote
                                total={transaction.items_total ?? null}
                                amount={transaction.amount}
                                currency={transaction.currency}
                            />
                        ) : null}
                    </div>

                    <section className="invoice-sheet__totals">
                        <div className="invoice-sheet__total-row">
                            <span>{t("common.amount", undefined, "المبلغ")}</span>
                            <Money amount={transaction.amount} currency={transaction.currency} className="fw-semibold" />
                        </div>
                        <div className="invoice-sheet__total-row">
                            <span>{t("transactions.payment_status", undefined, "حالة الدفع")}</span>
                            <PaymentStatusBadge status={transaction.payment_status} label={transaction.payment_status_label} />
                        </div>
                        <div className="invoice-sheet__total-row">
                            <span>{t("transactions.summary.remaining", undefined, "المتبقي")}</span>
                            <Money amount={transaction.outstanding} currency={transaction.currency} />
                        </div>
                        <div className="invoice-sheet__total-row invoice-sheet__total-row--grand">
                            <span>{t("transactions.invoice.grand_total", undefined, "الإجمالي")}</span>
                            <Money amount={transaction.amount} currency={transaction.currency} className="fw-bold" />
                        </div>
                    </section>

                    <footer className="invoice-sheet__footer">
                        <div>
                            <p className="invoice-sheet__label">{t("transactions.recorded_by", undefined, "سجّلها")}</p>
                            <p className="mb-0">{transaction.creator_name || "—"}</p>
                        </div>
                        <div className="invoice-sheet__signature">
                            <p className="invoice-sheet__label">{t("transactions.invoice.signature", undefined, "التوقيع")}</p>
                            <div className="invoice-sheet__signature-line" />
                        </div>
                    </footer>
                </article>

                <div className="row g-3">
                    <div className="col-12 col-lg-6">
                        <div className="app-section">
                            <div className="app-section__header">
                                <h2 className="h6 mb-0">{t("transactions.section.settlements", undefined, "الدفعات")}</h2>
                            </div>
                            <div className="app-section__body">
                                {settlements.length === 0 ? (
                                    <EmptyState title={t("transactions.settlements.empty", undefined, "لا توجد دفعات بعد")} />
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
                                        <TextInput
                                            id="settlement_date"
                                            name="settlement_date"
                                            type="date"
                                            label={t("transactions.settlement_date", undefined, "تاريخ الدفعة")}
                                            value={form.data.settlement_date}
                                            onChange={(v) => form.setData("settlement_date", v)}
                                            error={form.errors.settlement_date}
                                        />
                                        <TextInput
                                            id="settlement_amount"
                                            name="amount"
                                            label={t("common.amount", undefined, "المبلغ")}
                                            value={form.data.amount}
                                            onChange={(v) => form.setData("amount", v)}
                                            error={form.errors.amount}
                                        />
                                        <TextInput
                                            id="settlement_note"
                                            name="note"
                                            label={t("transactions.settlement_note_optional", undefined, "ملاحظة (اختياري)")}
                                            value={form.data.note}
                                            onChange={(v) => form.setData("note", v)}
                                            error={form.errors.note}
                                        />
                                        <button type="submit" className="btn btn-primary" style={{ minHeight: 48 }} disabled={form.processing}>
                                            {settleLabel}
                                        </button>
                                    </form>
                                ) : null}
                            </div>
                        </div>
                    </div>
                    <div className="col-12 col-lg-6">
                        <div className="app-section">
                            <div className="app-section__header">
                                <h2 className="h6 mb-0">{t("transactions.section.receipts", undefined, "الإيصالات والفواتير")}</h2>
                            </div>
                            <div className="app-section__body">
                                <DocumentList documents={documents} />
                                {can_upload ? (
                                    <div className="mt-3">
                                        <DocumentUploader enterpriseId={enterprise.public_id} transactionId={transaction.public_id} />
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
