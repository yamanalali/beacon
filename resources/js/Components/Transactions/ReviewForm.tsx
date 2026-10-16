import type { FormEvent } from "react";
import { SelectInput } from "../Forms/SelectInput";
import { TextInput } from "../Forms/TextInput";
import type { BookkeepingOptions, SuggestedTransactionItem } from "../../types/bookkeeping";
import { ItemsEditor, MismatchNote, itemsMismatch, itemsTotal, rowsFromSuggested, toItemsPayload, type ItemRow } from "./ItemsEditor";
import { Money } from "../Common/Money";
import { parseAmount } from "../../lib/money";

/**
 * Provider-agnostic draft of a transaction (OCR receipt today, voice later).
 * All suggested_* null => nothing was read => manual entry.
 */
export type TransactionDraft = {
    suggested_date: string | null;
    suggested_amount: string | null;
    suggested_currency: string | null;
    suggested_counterparty: string | null;
    confidence?: number | string | null;
    extractor?: string | null;
    needs_review?: boolean;
    /** Provider's guess of the type; preselects the type selector when present. */
    suggested_transaction_type?: string | null;
    /** Free-text note the provider read (voice). Optional: older servers don't send it. */
    suggested_note?: string | null;
    /** Receipt OCR: invoice number. The server already prefixes suggested_note with «فاتورة رقم X». */
    suggested_invoice_number?: string | null;
    /** Where the suggested currency came from; 'default' = enterprise default, not read/spoken. */
    suggested_currency_source?: string | null;
    /** Line items read from the receipt/voice (optional; unread values are null). */
    suggested_items?: SuggestedTransactionItem[] | null;
    /** Voice: items sum ≠ spoken total (non-blocking). */
    items_mismatch?: boolean;
    /** Voice: Arabic hint when the amount wording is ambiguous («3000 الاف») or items ≠ total. */
    suggested_amount_warning?: string | null;
};

export type ReviewFormData = {
    type: string;
    transaction_date: string;
    amount: string;
    currency: string;
    category: string;
    payment_status: string;
    paid_amount: string;
    counterparty: string;
    description: string;
    /** Invoice number (no backend column): kept in the note as a «فاتورة رقم X» prefix. */
    invoice_number: string;
    /** Optional line items (editor rows); sent as `items[]` via reviewPayload. */
    items: ItemRow[];
};

/** Submit payload: editor rows → `items[]` (fully empty rows dropped). */
export const INVOICE_PREFIX = "فاتورة رقم";
const PREFIX_RE = new RegExp(`^\\s*${INVOICE_PREFIX}\\s+\\S+(?:\\s*[-–—،,:]\\s*|\\s+|$)`);

/** Replaces/removes the leading «فاتورة رقم X» of a note so it always matches `invoiceNumber` (never duplicated). */
export function withInvoicePrefix(note: string, invoiceNumber: string): string {
    const rest = note.replace(PREFIX_RE, "").trim();
    const n = invoiceNumber.trim();
    if (!n) return rest;
    return rest ? `${INVOICE_PREFIX} ${n} - ${rest}` : `${INVOICE_PREFIX} ${n}`;
}

export function reviewPayload(data: ReviewFormData) {
    const { invoice_number, ...rest } = data;
    const description = invoice_number.trim() ? withInvoicePrefix(data.description, invoice_number) : data.description;
    return { ...rest, description, items: toItemsPayload(data.items) };
}

export type CategorySuggestion = { category: string; label: string; needs_review: boolean } | null;

export function hasSuggestions(d: TransactionDraft): boolean {
    return [d.suggested_date, d.suggested_amount, d.suggested_currency, d.suggested_counterparty].some((v) => v !== null && v !== "");
}

/** 0..1 or 0..100 → integer percent, or null. */
export function confidencePercent(c: TransactionDraft["confidence"]): number | null {
    if (c === null || c === undefined || c === "") return null;
    const n = Number(c);
    if (!Number.isFinite(n)) return null;
    return Math.round(n <= 1 ? n * 100 : n);
}

type TypeOption = { value: string; label: string };

/** Fields the user must fill when the reader could not (never defaulted to today / base currency). */
export const REQUIRED_FIELDS = ["transaction_date", "currency"] as const;
export function missingRequired(data: ReviewFormData): (typeof REQUIRED_FIELDS)[number][] {
    return REQUIRED_FIELDS.filter((k) => !data[k]);
}

/**
 * Initial form values from a draft. Unread date/currency stay empty (user must choose);
 * type comes from suggested_transaction_type and category from category_suggestion when valid.
 */
export function initialReviewData(draft: TransactionDraft, type: string, options: BookkeepingOptions, suggestion: CategorySuggestion, typeOptions?: TypeOption[]): ReviewFormData {
    const allowed = typeOptions?.length ? typeOptions : options.types;
    const resolvedType = draft.suggested_transaction_type && allowed.some((t) => t.value === draft.suggested_transaction_type) ? draft.suggested_transaction_type : type;
    const cats = options.categories[resolvedType];
    const category = suggestion?.category && (!cats || cats.length === 0 || cats.some((c) => c.value === suggestion.category)) ? suggestion.category : "";
    return {
        type: resolvedType,
        transaction_date: draft.suggested_date ?? "",
        amount: draft.suggested_amount ?? "",
        currency: draft.suggested_currency ?? "",
        category,
        payment_status: "paid",
        paid_amount: "",
        counterparty: draft.suggested_counterparty ?? "",
        description: typeof draft.suggested_note === "string" && draft.suggested_note.trim() !== "" ? draft.suggested_note : "",
        invoice_number: typeof draft.suggested_invoice_number === "string" ? draft.suggested_invoice_number.trim() : "",
        items: rowsFromSuggested(draft.suggested_items),
    };
}

/** Badge only when a non-empty suggestion was prefilled and the field still holds it. */
export function isPrefilled(suggested: string | null | undefined, current: string): boolean {
    return typeof suggested === "string" && suggested.trim() !== "" && current === suggested;
}

function Suggested({ show }: { show: boolean }) {
    return show ? (
        <span className="app-status app-status--warning ms-2" style={{ fontSize: "12px" }}>
            <i className="bi bi-stars" aria-hidden="true" /> مقترح
        </span>
    ) : null;
}

/** Review/confirm form. Reusable for any draft source; owns no network logic. */
export function ReviewForm({
    draft, data, setData, options, suggestion, errors = {}, processing = false, onSubmit, onReject, sourceLabel = "الإيصال", typeOptions,
}: {
    draft: TransactionDraft;
    data: ReviewFormData;
    setData: (patch: Partial<ReviewFormData>) => void;
    options: BookkeepingOptions;
    suggestion: CategorySuggestion;
    errors?: Partial<Record<keyof ReviewFormData, string>> & Record<string, string | undefined>;
    processing?: boolean;
    onSubmit: () => void;
    onReject?: () => void;
    sourceLabel?: string;
    /** `transaction_type_options` from the server when provided; defaults to options.types. */
    typeOptions?: { value: string; label: string }[];
}) {
    const manual = !hasSuggestions(draft);
    const pct = confidencePercent(draft.confidence);
    // Server decides (needs_review); no client-side confidence threshold.
    const low = draft.needs_review === true;
    const categories = options.categories[data.type] ?? [];
    const missing = missingRequired(data);
    const submit = (e: FormEvent) => { e.preventDefault(); if (missing.length === 0) onSubmit(); };
    const missingLabels = missing.map((k) => (k === "currency" ? "العملة" : "التاريخ")).join("، ");
    const amountUntouched = isPrefilled(draft.suggested_amount, data.amount);
    const amountWarning = draft.suggested_amount_warning && draft.suggested_amount_warning.trim() !== "" && amountUntouched ? draft.suggested_amount_warning : null;
    // Server flag only applies while amount and items are still what the server compared.
    const itemsUntouched = JSON.stringify(toItemsPayload(data.items)) === JSON.stringify(toItemsPayload(rowsFromSuggested(draft.suggested_items)));
    const showMismatch = itemsMismatch(data.items, data.amount, data.currency) || (draft.items_mismatch === true && amountUntouched && itemsUntouched);

    return (
        <form onSubmit={submit} noValidate aria-describedby="review-hint">
            <div id="review-hint" className={`alert ${manual ? "alert-info" : low ? "alert-warning" : "alert-success"} d-flex gap-2 align-items-start`} role="status">
                <i className={`bi ${manual ? "bi-pencil-square" : low ? "bi-exclamation-triangle" : "bi-check2-circle"}`} aria-hidden="true" />
                <div>
                    {manual ? (
                        <>
                            <strong>إدخال يدوي</strong>
                            <div className="small">لم نتمكن من قراءة {sourceLabel} تلقائياً. أدخل البيانات كما تظهر فيه ثم احفظ.</div>
                        </>
                    ) : (
                        <>
                            <strong>{low ? "راجع القيم بعناية" : "تمت القراءة"}</strong>
                            <div className="small">
                                القيم المعلّمة بـ «مقترح» قُرئت تلقائياً من {sourceLabel}، صحّحها إن لزم.
                                {pct !== null ? <> درجة الثقة: <bdi dir="ltr">{pct}%</bdi>.</> : null}
                            </div>
                        </>
                    )}
                </div>
            </div>

            <fieldset className="mb-3">
                <legend className="form-label mb-1 fw-semibold" style={{ fontSize: "0.9375rem" }}>نوع العملية</legend>
                <div className="d-flex flex-wrap gap-2" role="radiogroup">
                    {(typeOptions?.length ? typeOptions : options.types).map((t) => (
                        <label key={t.value} className={`btn ${data.type === t.value ? "btn-primary" : "btn-outline-primary"}`} style={{ minHeight: "44px" }}>
                            <input type="radio" className="visually-hidden" name="type" value={t.value} checked={data.type === t.value}
                                onChange={() => setData({ type: t.value, category: "" })} />
                            {t.label}
                        </label>
                    ))}
                </div>
                <Suggested show={isPrefilled(draft.suggested_transaction_type, data.type)} />
                {errors.type ? <div className="invalid-feedback d-block" role="alert">{errors.type}</div> : null}
            </fieldset>

            <div className="row g-2">
                <div className="col-7">
                    <TextInput id="amount" name="amount" label="المبلغ" value={data.amount} onChange={(v) => setData({ amount: v })} error={errors.amount} inputMode="decimal" />
                    <Suggested show={isPrefilled(draft.suggested_amount, data.amount)} />
                    {amountWarning ? (
                        <div className="alert alert-warning d-flex gap-2 align-items-start py-2 px-3 mt-1 mb-2 small" role="status" data-testid="amount-warning">
                            <i className="bi bi-exclamation-triangle" aria-hidden="true" />
                            <span>{amountWarning}</span>
                        </div>
                    ) : null}
                    {data.currency && parseAmount(data.amount) !== null ? (
                        <div className="form-text mt-n2" data-testid="amount-preview">
                            سيُحفظ: <Money amount={data.amount} currency={data.currency} />
                        </div>
                    ) : null}
                </div>
                <div className="col-5">
                    <SelectInput id="currency" label="العملة" required placeholder="اختر" value={data.currency} options={options.currencies} onChange={(v) => setData({ currency: v })} error={errors.currency} />
                    <Suggested show={isPrefilled(draft.suggested_currency, data.currency)} />
                {draft.suggested_currency_source === "default" && isPrefilled(draft.suggested_currency, data.currency) ? (
                    <div className="form-text" data-testid="currency-default-hint">العملة الافتراضية، تأكد منها</div>
                ) : null}
                </div>
            </div>
            <TextInput id="transaction_date" name="transaction_date" type="date" label="التاريخ" required placeholder="اختر" value={data.transaction_date} onChange={(v) => setData({ transaction_date: v })} error={errors.transaction_date} />
            <Suggested show={isPrefilled(draft.suggested_date, data.transaction_date)} />
            <TextInput id="counterparty" name="counterparty" label="المورد أو الزبون" value={data.counterparty} onChange={(v) => setData({ counterparty: v })} error={errors.counterparty} />
            <Suggested show={isPrefilled(draft.suggested_counterparty, data.counterparty)} />
            <SelectInput id="category" label="التصنيف" value={data.category} options={categories} placeholder="اختر التصنيف" onChange={(v) => setData({ category: v })} error={errors.category} />
            {suggestion && isPrefilled(suggestion.category, data.category) ? (
                <p className="small text-body-secondary mt-n2">
                    <Suggested show />
                    <span className="ms-1">{suggestion.label}{suggestion.needs_review ? " (يحتاج مراجعة)" : ""}</span>
                </p>
            ) : null}
            <SelectInput id="payment_status" label="هل تم الدفع؟" value={data.payment_status} options={options.payment_statuses} onChange={(v) => setData({ payment_status: v })} error={errors.payment_status} />
            {data.payment_status === "partially_paid" ? (
                <TextInput id="paid_amount" name="paid_amount" label="المبلغ المدفوع الآن" value={data.paid_amount} onChange={(v) => setData({ paid_amount: v })} error={errors.paid_amount} inputMode="decimal" />
            ) : null}
            {showMismatch ? <MismatchNote total={itemsTotal(data.items)} amount={data.amount} currency={data.currency} className="mb-2" testId="review-items-mismatch" /> : null}
            <ItemsEditor rows={data.items} onChange={(items) => setData({ items })} currency={data.currency} amount={data.amount} errors={errors} disabled={processing} hideMismatch />
            <TextInput id="invoice_number" name="invoice_number" label="رقم الفاتورة" value={data.invoice_number}
                onChange={(v) => setData({ invoice_number: v, description: withInvoicePrefix(data.description, v) })} />
            <Suggested show={isPrefilled(draft.suggested_invoice_number, data.invoice_number)} />
            <TextInput id="description" name="description" label="ملاحظة (اختياري)" value={data.description} onChange={(v) => setData({ description: v })} error={errors.description} />
            <Suggested show={isPrefilled(draft.suggested_note, data.description)} />

            {missing.length > 0 ? (
                <p id="review-missing" className="small text-danger mb-2" role="status">
                    <i className="bi bi-exclamation-circle me-1" aria-hidden="true" />
                    أكمل الحقول المطلوبة قبل التأكيد: {missingLabels}
                </p>
            ) : null}
            <div className="d-flex flex-column flex-sm-row gap-2 mt-2">
                <button type="submit" className="btn btn-primary flex-grow-1 flex-sm-grow-0" style={{ minHeight: "48px" }} disabled={processing || missing.length > 0} aria-describedby={missing.length > 0 ? "review-missing" : undefined}>
                    <i className="bi bi-check2 me-2" aria-hidden="true" />
                    {processing ? "جارٍ الحفظ..." : "تأكيد وحفظ العملية"}
                </button>
                {onReject ? (
                    <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "48px" }} disabled={processing} onClick={onReject}>
                        تجاهل
                    </button>
                ) : null}
            </div>
        </form>
    );
}

/** Name used by other draft sources (voice, …). */
export const TransactionDraftReview = ReviewForm;
