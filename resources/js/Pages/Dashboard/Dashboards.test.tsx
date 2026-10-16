import { cleanup, render, screen, within } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children, ...rest }: { href: string; children: ReactNode }) => <a href={href} {...rest}>{children}</a>,
    usePage: () => ({ props: { auth: {} } }),
    router: { get: vi.fn() },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));
vi.mock("../../Components/QuickRecord/QuickRecord", () => ({ QuickRecordButton: () => <button>سجّل معاملة</button> }));

import Sme from "./Sme";
import Beacon from "./Beacon";
import type { BeaconProgrammeData, SmeDashboardKpis } from "../../types/dashboard";

afterEach(cleanup);

const period = (income: string) => ({ from: "2026-10-01", to: "2026-10-31", income, expense: "200.00", net: "800.00", count: 3 });
const kpis: SmeDashboardKpis = {
    currency: "SYP", current_month: period("1000.00"), previous_month: period("500.00"), other_currencies: ["USD"], by_currency: { current_month: [], previous_month: [] }, unposted_count: 0,
    transaction_count: 9, pending_extractions_count: 2, readiness_score: "72", overdue_compliance_count: 1, upcoming_compliance_count: 0, open_escalations_count: 0,
};

describe("SME dashboard", () => {
    it("keeps the quick-record button and shows an empty state with no enterprise", () => {
        render(<Sme enterprise={null} kpis={null} />);
        expect(screen.getByRole("button", { name: "سجّل معاملة" })).toBeTruthy();
        expect(screen.getByText("لا توجد منشأة مرتبطة بحسابك بعد")).toBeTruthy();
        expect(screen.queryByLabelText("مؤشرات هذا الشهر")).toBeNull();
    });

    it("links alerts only when href is set and shows server KPIs verbatim", () => {
        render(
            <Sme
                enterprise={{ public_id: "E1", business_name: "متجر" }}
                kpis={kpis}
                alerts={[
                    { key: "pending_reviews", severity: "warning", count: 2, title: "فواتير بانتظار المراجعة", href: "/enterprises/E1/extractions/pending" },
                    { key: "open_escalations", severity: "info", count: 1, title: "استفسارات مفتوحة", href: null },
                ]}
                recent_transactions={[{ public_id: "T1", type: "sale", direction: "income", transaction_date: "2026-10-10", amount: "50.00", currency: "SYP", category: "مبيعات", counterparty: null, url: "/transactions/T1" }]}
                compliance={{ overdue: [{ id: 1, title: "تجديد الرخصة", due_date: "2026-10-01", status: "pending", overdue: true }], upcoming: [] }}
            />,
        );
        const alerts = screen.getByTestId("alerts");
        expect(within(alerts).getByText("فواتير بانتظار المراجعة").closest("a")?.getAttribute("href")).toBe("/enterprises/E1/extractions/pending");
        expect(within(alerts).getByText("استفسارات مفتوحة").closest("a")).toBeNull();
        expect(screen.getByText("\u20661,000 ل.س\u2069")).toBeTruthy();
        // previous 500 → current 1000 = +100%
        expect(screen.getByText("↑ \u2066100%\u2069 عن الشهر السابق")).toBeTruthy();
        expect(screen.getByTestId("currency-note").textContent).toContain("الدولار الأمريكي");
        expect(screen.getByTestId("currency-note").textContent).toContain("الليرة السورية");
        expect(screen.getByText("مبيعات").closest("a")?.querySelector("[data-money]")?.textContent).toBe("+50 ل.س");
        expect(screen.getByText("مبيعات").closest("a")?.getAttribute("href")).toBe("/transactions/T1");
        expect(screen.getByText("متأخر")).toBeTruthy();
    });

    it("hides the alerts card when there are none", () => {
        render(<Sme enterprise={{ public_id: "E1", business_name: "x" }} kpis={kpis} alerts={[]} />);
        expect(screen.queryByTestId("alerts")).toBeNull();
        expect(screen.getByText("لا توجد معاملات بعد")).toBeTruthy();
    });
});

const programme = (over: Partial<BeaconProgrammeData["kpis"]> = {}): BeaconProgrammeData => ({
    scope: "assigned",
    kpis: {
        enterprises_count: 4, active_enterprises_count: null, enterprises_by_readiness_band: null,
        open_escalations_count: 2, overdue_compliance_count: 3,
        referrals_by_status: [{ status: "shared", label: "مُرسلة", count: 1 }], ...over,
    },
    recent_referrals: [],
});

describe("Beacon dashboard", () => {
    it("hides null KPIs and shows an empty state for [] indicators", () => {
        render(<Beacon indicators={[]} programme={programme()} />);
        expect(screen.getByText("المنشآت المسندة إليك")).toBeTruthy();
        expect(screen.queryByText("منشآت نشطة")).toBeNull();
        expect(screen.queryByTestId("readiness-bands")).toBeNull();
        expect(screen.getByText("لم تُعتمد مؤشرات المتابعة بعد")).toBeTruthy();
        expect(screen.getByText("لا توجد إحالات حديثة")).toBeTruthy();
    });

    it("shows active count, bands and indicator groups when provided", () => {
        render(
            <Beacon
                indicators={[{ key: "usage", label: "x", value: "12" }]}
                programme={programme({ active_enterprises_count: 3, enterprises_by_readiness_band: [{ band: "مرتفعة", count: 2 }] })}
            />,
        );
        expect(screen.getByText("منشآت نشطة")).toBeTruthy();
        expect(within(screen.getByTestId("readiness-bands")).getByText("مرتفعة")).toBeTruthy();
        expect(screen.getByText("12")).toBeTruthy();
        expect(screen.queryByText("لم تُعتمد مؤشرات المتابعة بعد")).toBeNull();
    });

    it("renders without programme", () => {
        render(<Beacon />);
        expect(screen.queryByLabelText("مؤشرات البرنامج")).toBeNull();
    });
});

describe("SME previous month", () => {
    it("shows 'no data' when the previous month is zero", () => {
        const zero = { ...kpis, previous_month: { ...kpis.previous_month, income: "0.00", expense: "0.00" } };
        render(<Sme enterprise={{ public_id: "E1", business_name: "x" }} kpis={zero} />);
        expect(screen.getAllByText("لا توجد بيانات للشهر السابق").length).toBe(2);
    });
});
