/**
 * Single money formatter for the whole UI.
 * - Server sends raw decimal strings ("1000000.00", "-250.5"); parsed safely here.
 * - Latin digits, thousands separators (Intl.NumberFormat "en-US").
 * - Decimals from the currency's minor units (Intl), with SYP shown without decimals.
 * - Number first, then the symbol, always; wrap in an LTR isolate when placed in Arabic text.
 */

/** Display symbols in one place. Switch SYMBOL_MODE to "code" to show ISO codes (e.g. "SYP") instead. */
export const SYMBOL_MODE: "symbol" | "code" = "symbol";
export const CURRENCY_SYMBOLS: Record<string, string> = {
    SYP: "ل.س",
    USD: "$",
    AED: "د.إ",
    EUR: "€",
    SAR: "ر.س",
    TRY: "₺",
    GBP: "£",
};
/** Arabic currency names for explanatory text. */
export const CURRENCY_NAMES: Record<string, string> = {
    SYP: "الليرة السورية",
    USD: "الدولار الأمريكي",
    AED: "الدرهم الإماراتي",
    EUR: "اليورو",
    SAR: "الريال السعودي",
    TRY: "الليرة التركية",
    GBP: "الجنيه الإسترليني",
};
/** Overrides of Intl minor units (SYP has 2 in ISO 4217 but is used without fractions). */
const DIGIT_OVERRIDES: Record<string, number> = { SYP: 0 };

const ARABIC_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/g;

/** Parse "1,000,000.00", "١٢٣٫٥", " -250.5 ", 42 → number; anything else → null. */
export function parseAmount(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined) return null;
    if (typeof value === "number") return Number.isFinite(value) ? value : null;
    const s = value
        .trim()
        .replace(ARABIC_DIGITS, (d) => String((d.charCodeAt(0) & 0xf) % 10))
        .replace(/\u066B/g, ".")
        .replace(/[,\u066C\s]/g, "")
        .replace(/^\u2212/, "-");
    if (!/^[-+]?(\d+\.?\d*|\.\d+)$/.test(s)) return null;
    const n = Number(s);
    return Number.isFinite(n) ? n : null;
}

export function currencySymbol(currency: string | null | undefined): string {
    if (!currency) return "";
    const c = currency.toUpperCase();
    return SYMBOL_MODE === "code" ? c : (CURRENCY_SYMBOLS[c] ?? c);
}

export function currencyName(currency: string): string {
    return CURRENCY_NAMES[currency.toUpperCase()] ?? currency.toUpperCase();
}

const digitsCache = new Map<string, number>();
export function currencyDigits(currency: string | null | undefined): number {
    if (!currency) return 2;
    const c = currency.toUpperCase();
    if (c in DIGIT_OVERRIDES) return DIGIT_OVERRIDES[c];
    let d = digitsCache.get(c);
    if (d === undefined) {
        try {
            d = new Intl.NumberFormat("en-US", { style: "currency", currency: c }).resolvedOptions().maximumFractionDigits ?? 2;
        } catch {
            d = 2;
        }
        digitsCache.set(c, d);
    }
    return d;
}

const fmtCache = new Map<number, Intl.NumberFormat>();
function numberFormat(digits: number): Intl.NumberFormat {
    let f = fmtCache.get(digits);
    if (!f) {
        f = new Intl.NumberFormat("en-US", { minimumFractionDigits: digits, maximumFractionDigits: digits, useGrouping: true });
        fmtCache.set(digits, f);
    }
    return f;
}

/** Number only, with the currency's decimals. Unparseable → "—". */
export function formatAmount(amount: string | number | null | undefined, currency?: string | null): string {
    const n = parseAmount(amount);
    if (n === null) return "—";
    // Avoid "-0".
    const out = numberFormat(currencyDigits(currency)).format(n);
    return /^-0(\.0+)?$/.test(out) ? out.slice(1) : out;
}

/** "1,000,000 ل.س", "-1,250.50 $". Unparseable → "—". */
export function formatMoney(amount: string | number | null | undefined, currency: string | null | undefined): string {
    const num = formatAmount(amount, currency);
    if (num === "—") return num;
    const sym = currencySymbol(currency);
    return sym ? `${num} ${sym}` : num;
}

/** Same as formatMoney but wrapped in an LTR isolate (U+2066…U+2069) for plain-string contexts. */
export function formatMoneyIsolated(amount: string | number | null | undefined, currency: string | null | undefined): string {
    return `\u2066${formatMoney(amount, currency)}\u2069`;
}

/** Month-over-month change. null when previous is absent or zero (no comparison possible). */
export function percentChange(current: string | number | null | undefined, previous: string | number | null | undefined): number | null {
    const c = parseAmount(current);
    const p = parseAmount(previous);
    if (c === null || p === null || p === 0) return null;
    return ((c - p) / Math.abs(p)) * 100;
}

export const NO_PREVIOUS = "لا توجد بيانات للشهر السابق";

/** "↑ 12.5% عن الشهر السابق" / "↓ 3% …" / "بدون تغيير عن الشهر السابق" / NO_PREVIOUS. */
export function changeLabel(current: string | number | null | undefined, previous: string | number | null | undefined): string {
    const pct = percentChange(current, previous);
    if (pct === null) return NO_PREVIOUS;
    const r = Math.round(pct * 10) / 10;
    if (r === 0) return "بدون تغيير عن الشهر السابق";
    const v = new Intl.NumberFormat("en-US", { maximumFractionDigits: 1 }).format(Math.abs(r));
    return `${r > 0 ? "↑" : "↓"} \u2066${v}%\u2069 عن الشهر السابق`;
}
