import { describe, expect, it } from "vitest";
import { changeLabel, currencyDigits, formatAmount, formatMoney, formatMoneyIsolated, parseAmount, percentChange, NO_PREVIOUS } from "./money";

describe("parseAmount", () => {
    it("parses raw server strings and rejects junk", () => {
        expect(parseAmount("1000000.00")).toBe(1000000);
        expect(parseAmount("-250.5")).toBe(-250.5);
        expect(parseAmount("1,234.5")).toBe(1234.5);
        expect(parseAmount("١٢٣٫٥")).toBe(123.5);
        expect(parseAmount(42)).toBe(42);
        expect(parseAmount("")).toBeNull();
        expect(parseAmount("abc")).toBeNull();
        expect(parseAmount("1.2.3")).toBeNull();
        expect(parseAmount(null)).toBeNull();
        expect(parseAmount(Number.NaN)).toBeNull();
    });
});

describe("formatMoney", () => {
    it("SYP: no decimals, thousands separators, Latin digits, ل.س after the number", () => {
        expect(formatMoney("1000000.00", "SYP")).toBe("1,000,000 ل.س");
        expect(formatMoney("435000", "SYP")).toBe("435,000 ل.س");
        expect(currencyDigits("SYP")).toBe(0);
    });
    it("USD/AED/EUR: 2 decimals from Intl minor units", () => {
        expect(formatMoney("1250.5", "USD")).toBe("1,250.50 $");
        expect(formatMoney("40", "AED")).toBe("40.00 د.إ");
        expect(formatMoney(9.999, "EUR")).toBe("10.00 €");
        expect(currencyDigits("JPY")).toBe(0);
        expect(formatMoney("1000", "XYZ")).toBe("1,000.00 XYZ");
    });
    it("negatives and zero", () => {
        expect(formatMoney("-1250.50", "USD")).toBe("-1,250.50 $");
        expect(formatMoney("-35000.00", "SYP")).toBe("-35,000 ل.س");
        expect(formatMoney("-0.00", "SYP")).toBe("0 ل.س");
        expect(formatMoney("0.00", "USD")).toBe("0.00 $");
    });
    it("unparseable → dash; isolate wrapper; amount only", () => {
        expect(formatMoney(null, "SYP")).toBe("—");
        expect(formatMoney("x", "USD")).toBe("—");
        expect(formatMoneyIsolated("5", "USD")).toBe("\u20665.00 $\u2069");
        expect(formatAmount("1234567.891", null)).toBe("1,234,567.89");
        expect(formatMoney("1000", null)).toBe("1,000.00");
    });
});

describe("month change", () => {
    it("no previous data when previous is 0 or missing", () => {
        expect(percentChange("100", "0.00")).toBeNull();
        expect(changeLabel("100", "0.00")).toBe(NO_PREVIOUS);
        expect(changeLabel("100", null)).toBe(NO_PREVIOUS);
    });
    it("up/down arrows with percent", () => {
        expect(percentChange("150", "100")).toBe(50);
        expect(changeLabel("150", "100")).toBe("↑ \u206650%\u2069 عن الشهر السابق");
        expect(changeLabel("90", "120")).toBe("↓ \u206625%\u2069 عن الشهر السابق");
        expect(changeLabel("-50", "-100")).toBe("↑ \u206650%\u2069 عن الشهر السابق");
        expect(changeLabel("100", "100")).toBe("بدون تغيير عن الشهر السابق");
    });
});
