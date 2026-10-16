import { formatMoney, parseAmount } from "../../lib/money";

/**
 * Number formatting shared by tables and KPI tiles. Latin digits with thousands separators, shown LTR.
 * Money ("currency") always goes through lib/money formatMoney, the single formatter for amounts.
 */
const cache = new Map<string, Intl.NumberFormat>();

export type NumberFormat = "number" | "integer" | "currency" | "percent";

function nf(key: string, opts: Intl.NumberFormatOptions): Intl.NumberFormat {
    let f = cache.get(key);
    if (!f) {
        f = new Intl.NumberFormat("en-US", { useGrouping: true, ...opts });
        cache.set(key, f);
    }
    return f;
}

export function formatNumber(value: unknown, format: NumberFormat = "number", currency?: string | null): string {
    if (value === null || value === undefined || value === "") return "—";
    const n = typeof value === "number" || typeof value === "string" ? parseAmount(value) : null;
    if (n === null) return String(value);
    if (format === "currency") return formatMoney(n, currency ?? null);
    if (format === "integer") return nf("integer", { maximumFractionDigits: 0 }).format(n);
    if (format === "percent") return `${nf("percent", { maximumFractionDigits: 1 }).format(n)}%`;
    return nf("number", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}