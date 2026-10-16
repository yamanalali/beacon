import { cleanup, render } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { Money } from "./Money";
import { currencyColumn, isAmountCell } from "../../Pages/FinancialReports/Show";

afterEach(cleanup);

describe("<Money>", () => {
    it("renders an LTR isolate with the formatted amount and optional sign", () => {
        const { container } = render(<p>المبلغ <Money amount="1000000.00" currency="SYP" signed="out" /></p>);
        const bdi = container.querySelector("bdi")!;
        expect(bdi.getAttribute("dir")).toBe("ltr");
        expect(bdi.textContent).toBe("−1,000,000 ل.س");
    });
    it("shows a dash without sign for missing amounts", () => {
        const { container } = render(<Money amount={null} currency="USD" signed="in" />);
        expect(container.textContent).toBe("—");
    });
});

describe("report cells", () => {
    it("detects decimal amount cells and the currency column", () => {
        expect(isAmountCell("1000000.00")).toBe(true);
        expect(isAmountCell("-35.5")).toBe(true);
        expect(isAmountCell("2026-10-01")).toBe(false);
        expect(isAmountCell("12")).toBe(false);
        expect(currencyColumn(["التاريخ", "العملة", "المبلغ"])).toBe(1);
        expect(currencyColumn(["البند", "المبلغ"])).toBe(-1);
    });
});
