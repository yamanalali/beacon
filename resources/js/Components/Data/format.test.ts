import { describe, expect, it } from "vitest";
import { formatNumber } from "./format";
import { formatMoney } from "../../lib/money";

describe("formatNumber (routed through lib/money for amounts)", () => {
    it("currency uses formatMoney: SYP 0 dp, USD 2 dp, negatives, symbols", () => {
        expect(formatNumber("1000000.00", "currency", "SYP")).toBe("1,000,000 ل.س");
        expect(formatNumber("1000000.00", "currency", "SYP")).toBe(formatMoney("1000000.00", "SYP"));
        expect(formatNumber("-1250.5", "currency", "USD")).toBe("-1,250.50 $");
        expect(formatNumber(40, "currency", "AED")).toBe("40.00 د.إ");
        expect(formatNumber("1000", "currency")).toBe("1,000.00");
    });
    it("non-money numbers: Latin digits and thousands separators", () => {
        expect(formatNumber(1000000)).toBe("1,000,000.00");
        expect(formatNumber("1,234,567.891")).toBe("1,234,567.89");
        expect(formatNumber("2500", "integer")).toBe("2,500");
        expect(formatNumber("١٢٣٤٥", "integer")).toBe("12,345");
        expect(formatNumber(12.345, "percent")).toBe("12.3%");
        expect(formatNumber("-3000", "integer")).toBe("-3,000");
    });
    it("empty and unparseable values", () => {
        expect(formatNumber(null)).toBe("—");
        expect(formatNumber("")).toBe("—");
        expect(formatNumber("n/a")).toBe("n/a");
        expect(formatNumber({})).toBe("[object Object]");
    });
});