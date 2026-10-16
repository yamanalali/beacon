import { formatMoney } from "../../lib/money";
import { Money } from "../Common/Money";
import { EmptyState } from "../Feedback/EmptyState";
import type { TransactionItem } from "../../types/bookkeeping";
import { computeLineTotal, emptyRow, itemsMismatch, itemsTotal, rowTotal, type ItemRow } from "./itemsModel";

export * from "./itemsModel";

const isPrefilled = (s: string | null | undefined, v: string) => typeof s === "string" && s.trim() !== "" && s === v;

function Badge({ show }: { show: boolean }) {
    return show ? (
        <span className="app-status app-status--warning ms-1" style={{ fontSize: "11px" }}>
            <i className="bi bi-stars" aria-hidden="true" /> مقترح
        </span>
    ) : null;
}

function MismatchNote({ total, amount, currency }: { total: number | null; amount: string | number; currency: string }) {
    return (
        <div className="alert alert-warning d-flex gap-2 align-items-start mb-0 mt-2" role="status" data-testid="items-mismatch">
            <i className="bi bi-exclamation-triangle" aria-hidden="true" />
            <div className="small">
                مجموع البنود <Money amount={total} currency={currency} /> لا يساوي مبلغ العملية <Money amount={amount} currency={currency} />. يمكنك الحفظ رغم
                ذلك؛ راجع الفرق (ضريبة أو خصم أو توصيل مثلاً).
            </div>
        </div>
    );
}

type Props = {
    rows: ItemRow[];
    onChange: (rows: ItemRow[]) => void;
    currency: string;
    /** Transaction amount for the (non-blocking) mismatch warning. */
    amount: string;
    /** Server errors keyed like "items.0.description". */
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
};

/** Optional invoice line items: editable table on desktop, stacked cards on mobile. */
export function ItemsEditor({ rows, onChange, currency, amount, errors = {}, disabled }: Props) {
    const total = itemsTotal(rows);
    const mismatch = itemsMismatch(rows, amount);
    const update = (key: string, patch: Partial<ItemRow>) => onChange(rows.map((r) => (r.key === key ? { ...r, ...patch } : r)));
    const add = () => onChange([...rows, emptyRow()]);
    const remove = (key: string) => onChange(rows.filter((r) => r.key !== key));
    const listError = errors.items;

    return (
        <section className="mb-3" aria-labelledby="items-heading" data-testid="items-editor">
            <div className="d-flex justify-content-between align-items-center mb-2">
                <h3 id="items-heading" className="h6 mb-0">
                    البنود <span className="text-body-secondary fw-normal small">(اختياري)</span>
                </h3>
                {rows.length > 0 ? (
                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={add} disabled={disabled}>
                        <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                        إضافة بند
                    </button>
                ) : null}
            </div>
            {listError ? <div className="invalid-feedback d-block mb-2" role="alert">{listError}</div> : null}
            {rows.length === 0 ? (
                <div className="border rounded-3">
                    <EmptyState icon="bi-list-ul" title="لا توجد بنود" description="أضف بنود الفاتورة إن أردت تفصيل المبلغ." />
                    <div className="text-center pb-3">
                        <button type="button" className="btn btn-outline-primary" onClick={add} disabled={disabled}>
                            <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                            إضافة بند
                        </button>
                    </div>
                </div>
            ) : (
                <>
                    <div className="d-none d-md-flex row g-2 small text-body-secondary fw-semibold px-1" aria-hidden="true">
                        <div className="col-md-4">الوصف</div>
                        <div className="col-md-2">الكمية</div>
                        <div className="col-md-2">سعر الوحدة</div>
                        <div className="col-md-3">الإجمالي</div>
                        <div className="col-md-1" />
                    </div>
                    <ul className="list-unstyled mb-0" aria-label="بنود العملية">
                        {rows.map((r, i) => {
                            const n = i + 1;
                            const e = (f: string) => errors[`items.${i}.${f}`];
                            const computed = computeLineTotal(r.quantity, r.unit_price);
                            const shownTotal = r.total_manual ? r.total : computed === null ? "" : String(computed);
                            const field = (f: keyof Pick<ItemRow, "description" | "quantity" | "unit_price" | "total">, label: string, value: string, onValue: (v: string) => void, opts: { decimal?: boolean; col: string }) => (
                                <div className={opts.col}>
                                    <label htmlFor={`${r.key}-${f}`} className="form-label small mb-1 d-md-none">
                                        {label}
                                    </label>
                                    <input
                                        id={`${r.key}-${f}`}
                                        className={`form-control${e(f) ? " is-invalid" : ""}`}
                                        style={{ minHeight: "44px", fontSize: "16px" }}
                                        value={value}
                                        inputMode={opts.decimal ? "decimal" : undefined}
                                        dir={opts.decimal ? "ltr" : undefined}
                                        aria-label={`${label} — البند ${n}`}
                                        aria-invalid={e(f) ? true : undefined}
                                        disabled={disabled}
                                        onChange={(ev) => onValue(ev.target.value)}
                                    />
                                    <Badge show={isPrefilled(r.suggested?.[f], value)} />
                                    {e(f) ? <div className="invalid-feedback d-block">{e(f)}</div> : null}
                                </div>
                            );
                            return (
                                <li key={r.key} className="row g-2 align-items-start py-2 border-bottom" data-testid="item-row">
                                    {field("description", "الوصف", r.description, (v) => update(r.key, { description: v }), { col: "col-12 col-md-4" })}
                                    {field("quantity", "الكمية", r.quantity, (v) => update(r.key, { quantity: v }), { decimal: true, col: "col-4 col-md-2" })}
                                    {field("unit_price", "سعر الوحدة", r.unit_price, (v) => update(r.key, { unit_price: v }), { decimal: true, col: "col-4 col-md-2" })}
                                    {field("total", "الإجمالي", shownTotal, (v) => update(r.key, { total: v, total_manual: v.trim() !== "" }), { decimal: true, col: "col-4 col-md-3" })}
                                    <div className="col-12 col-md-1 d-flex justify-content-end">
                                        <button type="button" className="app-icon-button" aria-label={`حذف البند ${n}`} onClick={() => remove(r.key)} disabled={disabled}>
                                            <i className="bi bi-trash" aria-hidden="true" />
                                        </button>
                                    </div>
                                    {r.total_manual && computed !== null && Math.round(computed * 100) !== Math.round((rowTotal(r) ?? 0) * 100) ? (
                                        <div className="col-12 small text-body-secondary">
                                            الإجمالي المكتوب يختلف عن الكمية × السعر (<bdi dir="ltr">{formatMoney(computed, currency)}</bdi>).
                                        </div>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                    <div className="d-flex justify-content-between align-items-center pt-2 fw-semibold" data-testid="items-total">
                        <span>مجموع البنود</span>
                        <Money amount={total} currency={currency} />
                    </div>
                    {mismatch ? <MismatchNote total={total} amount={amount} currency={currency} /> : null}
                </>
            )}
        </section>
    );
}

/** Read-only items table (Transactions/Show). */
export function ItemsTable({ items, itemsTotal: total, mismatch, amount, currency }: { items: TransactionItem[]; itemsTotal: number | null; mismatch: boolean; amount: string; currency: string }) {
    if (items.length === 0) return <EmptyState icon="bi-list-ul" title="لا توجد بنود لهذه العملية" />;
    return (
        <div data-testid="items-table">
            <div className="table-responsive d-none d-md-block">
                <table className="table align-middle mb-0">
                    <caption className="visually-hidden">بنود العملية</caption>
                    <thead>
                        <tr>
                            <th scope="col">الوصف</th>
                            <th scope="col">الكمية</th>
                            <th scope="col">سعر الوحدة</th>
                            <th scope="col">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((i) => (
                            <tr key={i.id}>
                                <td>{i.description}</td>
                                <td><bdi dir="ltr">{new Intl.NumberFormat("en-US", { maximumFractionDigits: 3 }).format(i.quantity)}</bdi></td>
                                <td><Money amount={i.unit_price} currency={currency} /></td>
                                <td><Money amount={i.total} currency={currency} /></td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="fw-semibold">
                            <td colSpan={3}>مجموع البنود</td>
                            <td><Money amount={total} currency={currency} /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <ul className="list-unstyled mb-0 d-md-none">
                {items.map((i) => (
                    <li key={i.id} className="app-mobile-record mb-2">
                        <div className="fw-semibold">{i.description}</div>
                        <div className="d-flex justify-content-between small">
                            <span>
                                <bdi dir="ltr">{new Intl.NumberFormat("en-US", { maximumFractionDigits: 3 }).format(i.quantity)}</bdi> × <Money amount={i.unit_price} currency={currency} />
                            </span>
                            <Money amount={i.total} currency={currency} className="fw-semibold" />
                        </div>
                    </li>
                ))}
                <li className="d-flex justify-content-between fw-semibold pt-1">
                    <span>مجموع البنود</span>
                    <Money amount={total} currency={currency} />
                </li>
            </ul>
            {mismatch ? <MismatchNote total={total} amount={amount} currency={currency} /> : null}
        </div>
    );
}
