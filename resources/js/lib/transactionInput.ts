/** Normalize localized decimal input without guessing a comma's meaning or using floats. */
export function normalizeTransactionAmount(value: string): string {
    const translated = value.trim()
        .replace(/[\u0660-\u0669\u06f0-\u06f9]/g, d => String((d.charCodeAt(0) & 15) % 10))
        .replace(/\u066b/g, ".").replace(/\u066c/g, ",");
    if (translated.includes(",") && !/^\d{1,3}(,\d{3})+(\.\d+)?$/.test(translated)) return translated;
    return translated.replaceAll(",", "");
}
export function transactionMinor(value: string | null | undefined): bigint | null {
    if (value === null || value === undefined) return null;
    const normalized = normalizeTransactionAmount(value);
    if (!/^\d{1,16}(\.\d{1,2})?$/.test(normalized)) return null;
    const [whole, fraction = ""] = normalized.split(".");
    return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
}
export function exactTransactionMoney(minor: bigint, currency: string): string {
    return `${(minor / 100n).toLocaleString("en-US")}.${(minor % 100n).toString().padStart(2, "0")} ${currency}`;
}
export function transactionToday(now = new Date()): string {
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
}
