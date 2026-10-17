import { parseAmount } from "../../lib/money";
import type { SuggestedTransactionItem, TransactionItem, TransactionItemInput } from "../../types/bookkeeping";

/** Editable line item. Inputs are strings; `total_manual` = user (or source) typed a total. */
export type ItemRow = {
    key: string;
    description: string;
    quantity: string;
    unit_price: string;
    total: string;
    total_manual: boolean;
    /** Values prefilled from a receipt/voice draft (for the «مقترح» badge). */
    suggested?: { description: string | null; quantity: string | null; unit_price: string | null; total: string | null };
};

let seq = 0;
const newKey = () => `item-${Date.now().toString(36)}-${(seq++).toString(36)}`;

export function emptyRow(): ItemRow {
    return { key: newKey(), description: "", quantity: "1", unit_price: "", total: "", total_manual: false };
}

const str = (v: number | string | null | undefined): string => (v === null || v === undefined ? "" : String(v));

/** quantity × unit_price rounded half up to 2 dp (same rule as the server). */
export function computeLineTotal(quantity: string, unitPrice: string): number | null {
    const q = parseAmount(quantity);
    const p = parseAmount(unitPrice);
    if (q === null || p === null) return null;
    return Math.round((q * p + Number.EPSILON) * 100) / 100;
}

export function rowTotal(r: ItemRow): number | null {
    if (r.total_manual) {
        const t = parseAmount(r.total);
        if (t !== null) return t;
    }
    return computeLineTotal(r.quantity, r.unit_price);
}

/** Fully empty = nothing typed in description, price or total (default quantity "1" alone doesn't count). */
export function isEmptyRow(r: ItemRow): boolean {
    return r.description.trim() === "" && r.unit_price.trim() === "" && r.total.trim() === "" && (r.quantity.trim() === "" || r.quantity.trim() === "1");
}

export function itemsTotal(rows: ItemRow[]): number | null {
    const used = rows.filter((r) => !isEmptyRow(r));
    if (used.length === 0) return null;
    return Math.round(used.reduce((s, r) => s + (rowTotal(r) ?? 0), 0) * 100) / 100;
}

/** Items total differs from the transaction amount (warning only; never blocks saving). */
/** Allowed rounding difference between items total and amount: SYP has no minor unit (<= 0.5), others <= 0.005. */
export function mismatchTolerance(currency?: string | null): number {
    return (currency ?? "").toUpperCase() === "SYP" ? 0.5 : 0.005;
}

export function itemsMismatch(rows: ItemRow[], amount: string | number | null | undefined, currency?: string | null): boolean {
    const t = itemsTotal(rows);
    const a = parseAmount(amount ?? null);
    if (t === null || a === null) return false;
    return Math.abs(t - a) > mismatchTolerance(currency) + 1e-9;
}

/** Request shape `items[]`: fully empty rows dropped; `total` only when typed. */
export function toItemsPayload(rows: ItemRow[]): TransactionItemInput[] {
    return rows
        .filter((r) => !isEmptyRow(r))
        .map((r) => {
            const out: TransactionItemInput = { description: r.description.trim(), quantity: r.quantity.trim(), unit_price: r.unit_price.trim() };
            if (r.total_manual && r.total.trim() !== "") out.total = r.total.trim();
            return out;
        });
}

/** Saved items (numbers) → editable rows; a stored total equal to q×p is treated as computed. */
export function rowsFromItems(items: TransactionItem[] | null | undefined): ItemRow[] {
    return (items ?? []).map((i) => {
        const computed = computeLineTotal(str(i.quantity), str(i.unit_price));
        return {
            key: `saved-${i.id}`,
            description: i.description ?? "",
            quantity: str(i.quantity),
            unit_price: str(i.unit_price),
            total: str(i.total),
            total_manual: computed === null || Math.round(computed * 100) !== Math.round(i.total * 100),
        };
    });
}

/** Draft suggestions → rows. Null values stay empty (no guessing); all-null items are skipped. */
export function rowsFromSuggested(items: SuggestedTransactionItem[] | null | undefined): ItemRow[] {
    if (!Array.isArray(items)) return [];
    return items
        .filter((i) => i && [i.description, i.quantity, i.unit_price, i.total].some((v) => v !== null && v !== undefined && String(v).trim() !== ""))
        .map((i, n) => {
            const s = {
                description: i.description ?? null,
                quantity: i.quantity === null || i.quantity === undefined ? null : String(i.quantity),
                unit_price: i.unit_price === null || i.unit_price === undefined ? null : String(i.unit_price),
                total: i.total === null || i.total === undefined ? null : String(i.total),
            };
            return {
                key: `suggested-${n}`,
                description: s.description ?? "",
                quantity: s.quantity ?? "",
                unit_price: s.unit_price ?? "",
                total: s.total ?? "",
                total_manual: s.total !== null,
                suggested: s,
            };
        });
}
