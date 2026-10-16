/** Number formatting shared by tables and KPI tiles. Latin digits with thousands separators, shown LTR. */
const cache = new Map<string, Intl.NumberFormat>();

export type NumberFormat = "number" | "integer" | "currency" | "percent";

export function formatNumber(value: unknown, format: NumberFormat = "number", currency?: string): string {
    if (value === null || value === undefined || value === "") return "—";
    const n = typeof value === "number" ? value : Number(String(value).replace(/,/g, ""));
    if (!Number.isFinite(n)) return String(value);
    const key = `${format}:${currency ?? ""}`;
    let f = cache.get(key);
    if (!f) {
        const opts: Intl.NumberFormatOptions =
            format === "integer"
                ? { maximumFractionDigits: 0 }
                : format === "percent"
                  ? { maximumFractionDigits: 1 }
                  : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
        f = new Intl.NumberFormat("en-US", opts);
        cache.set(key, f);
    }
    const out = f.format(n);
    if (format === "percent") return `${out}%`;
    if (format === "currency" && currency) return `${out} ${currency}`;
    return out;
}
