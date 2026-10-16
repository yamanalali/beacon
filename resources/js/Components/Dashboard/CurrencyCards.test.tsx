import { cleanup, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { CurrencyCards, orderCurrencies } from "./CurrencyCards";
import type { CurrencyTotals } from "../../types/dashboard";

afterEach(cleanup);

const t = (currency: string, income: string, expense: string, net: string, n: number): CurrencyTotals => ({ currency, income_total: income, expense_total: expense, net, transaction_count: n });

describe("CurrencyCards", () => {
    it("renders one card per currency, SYP first, no conversion, coloured net", () => {
        render(<CurrencyCards kpis={{ unposted_count: 0, by_currency: {
            current_month: [t("USD", "100.00", "250.00", "-150.00", 2), t("SYP", "200000", "50000", "150000", 5)],
            previous_month: [t("SYP", "100000", "50000", "50000", 3)],
        } }} />);
        const cards = screen.getAllByTestId("currency-card");
        expect(cards).toHaveLength(2);
        expect(cards[0].textContent).toContain("ل.س");
        expect(within(cards[0]).getByTestId("cc-income").textContent).toContain("200,000");
        expect(within(cards[1]).getByTestId("cc-income").textContent).toContain("100.00");
        expect(within(cards[0]).getByTestId("cc-net").getAttribute("style")).toContain("var(--app-success)");
        expect(within(cards[1]).getByTestId("cc-net").getAttribute("style")).toContain("var(--app-danger)");
        expect(cards[0].textContent).toContain("5");
        expect(screen.queryByTestId("unposted-line")).toBeNull();
    });

    it("shows change vs last month per currency", () => {
        render(<CurrencyCards kpis={{ unposted_count: 0, by_currency: { current_month: [t("SYP", "200000", "50000", "150000", 5)], previous_month: [t("SYP", "100000", "50000", "50000", 3)] } }} />);
        expect(screen.getByTestId("currency-card").textContent).toMatch(/100/); // +100% income
    });

    it("shows the unposted line only when > 0", () => {
        render(<CurrencyCards kpis={{ unposted_count: 3, by_currency: { current_month: [t("SYP", "0", "0", "0", 0)], previous_month: [] } }} />);
        expect(screen.getByTestId("unposted-line").textContent?.replace(/\s+/g, " ").trim()).toBe("3 فواتير ناطرة مراجعة");
    });

    it("keeps server order for non-SYP currencies", () => {
        expect(orderCurrencies([t("USD", "0", "0", "0", 0), t("EUR", "0", "0", "0", 0), t("SYP", "0", "0", "0", 0)]).map((x) => x.currency)).toEqual(["SYP", "USD", "EUR"]);
    });
});
