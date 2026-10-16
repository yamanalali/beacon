import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { PackageView } from "./PackageView";
import type { ReferralPackage } from "../../types/referrals";

afterEach(cleanup);

// Contract shape of ReferralPackageBuilder (#78). Mock values only.
const pkg: ReferralPackage = {
    enterprise_profile: { business_name: "ورشة الياسمين", sector: "textiles", sector_label: "خياطة ونسيج", location: "دمشق", founding_date: "2020-03-15", employee_count: 4, registration_status: "registered", registration_status_label: "مسجلة" },
    financial_summary: {
        period: { from: "2025-10-16", to: "2026-10-16" },
        currencies: [
            { currency: "USD", income_total: "1200.50", expense_total: "1500.00", net: "-299.50", transaction_count: 7 },
            { currency: "SYP", income_total: "1500000", expense_total: "250000", net: "1250000", transaction_count: 12 },
        ],
    },
    documents: [
        { name: "السجل التجاري", type: "PDF", date: "2026-09-01" },
        { name: "أ عقد إيجار", type: "صورة", date: "2026-10-01" },
    ],
};

describe("PackageView (referral package)", () => {
    it("shows sector/registration labels instead of raw codes", () => {
        const { container } = render(<PackageView pkg={pkg} />);
        const t = container.textContent ?? "";
        expect(t).toContain("خياطة ونسيج");
        expect(t).toContain("مسجلة");
        expect(t).not.toContain("textiles");
        expect(t).not.toContain("registered");
    });

    it("renders one card per currency, SYP first, with period, money, coloured net and count", () => {
        render(<PackageView pkg={pkg} />);
        const cards = screen.getAllByTestId("fin-currency");
        expect(cards).toHaveLength(2);
        expect(cards[0].textContent).toContain("SYP");
        expect(cards[0].textContent).toContain("12");
        expect(screen.getByTestId("fin-period").textContent).toMatch(/الفترة من .*2025.* إلى .*2026/);
        const nets = screen.getAllByTestId("fin-net");
        expect(nets[0].getAttribute("style")).toContain("var(--app-success)");
        expect(nets[1].getAttribute("style")).toContain("var(--app-danger)");
        expect(nets[1].textContent).toContain("299.50");
    });

    it("sorts documents by column and has mobile cards", () => {
        render(<PackageView pkg={pkg} />);
        const table = screen.getByTestId("docs-table");
        const names = () => within(table).getAllByRole("row").slice(1).map((r) => (r as HTMLTableRowElement).cells[0].textContent);
        expect(names()).toEqual(["أ عقد إيجار", "السجل التجاري"]); // newest first
        fireEvent.click(within(table).getByRole("button", { name: /الاسم/ }));
        expect(names()).toEqual(["أ عقد إيجار", "السجل التجاري"].sort((a, b) => a.localeCompare(b, "ar")));
        fireEvent.click(within(table).getByRole("button", { name: /الاسم/ }));
        expect(names()).toEqual(["أ عقد إيجار", "السجل التجاري"].sort((a, b) => b.localeCompare(a, "ar")));
        expect(within(screen.getByTestId("docs-cards")).getAllByText(/السجل التجاري|عقد إيجار/)).toHaveLength(2);
    });

    it("shows Arabic empty states for undisclosed and empty sections, never JSON", () => {
        const { container } = render(<PackageView pkg={{ financial_summary: { period: { from: "2026-01-01", to: "2026-10-16" }, currencies: [] }, documents: [] }} />);
        expect(screen.getByTestId("empty-enterprise_profile")).toBeTruthy();
        expect(screen.getByTestId("empty-readiness_assessment")).toBeTruthy();
        expect(screen.getByTestId("empty-documents")).toBeTruthy();
        expect(screen.getByText("لا توجد معاملات")).toBeTruthy();
        const t = container.textContent ?? "";
        expect(t).not.toMatch(/[{}[\]]/);
        expect(container.querySelector("pre")).toBeNull();
    });
});
