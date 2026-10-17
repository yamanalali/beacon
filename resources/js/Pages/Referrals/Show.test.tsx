import { cleanup, render, screen, within } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { post: vi.fn() }, usePage: () => ({ props: { errors: {} } }) }));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));

import Show from "./Show";
import type { ReferralPackage } from "../../types/referrals";
import { humanizeKey } from "../../Components/Referrals/PackageView";

afterEach(cleanup);

const pkg = {
    enterprise_profile: { business_name: "ورشة الياسمين", sector: "خياطة", location: "دمشق", founding_date: "2020-03-15", employee_count: 4, registration_status: null, id: 99 },
    readiness_assessment: { score: "72.50", mandatory_conditions_met: true, band: "متوسطة", finalized_at: "2026-10-10 14:30:00", rule_set_version: 3, disclaimer: "نتيجة الجاهزية أداة استرشادية وليست موافقة على التمويل." },
    compliance_status: [{ requirement: "سجل تجاري", version: 1, status: "مكتمل" }, { requirement: "ترخيص", version: 2, status: "قيد التنفيذ" }],
    financial_summary: { currency: "SYP", revenue: 1500000, expenses: "250000.00", period_end: "2026-09-30" },
    documents: [{ title: "السجل التجاري", type: "registration", uploaded_at: "2026-09-01", storage_path: "x/y.pdf", public_id: "01ABC" }],
    risk_flags: ["late_payments"],
    extra_meta: { weird_field: { nested_key: true } },
    // Deliberately off-contract: PackageView must stay readable for unknown/legacy shapes.
} as unknown as ReferralPackage;

const props = {
    referral: { public_id: "R1", institution: "مؤسسة التمويل", status: "shared", status_label: "تمت المشاركة", purpose_ar: "تمويل معدات", disclosed_items: [{ key: "enterprise_profile", label: "الملف التعريفي" }], info_request_note: "أرسلوا كشف حساب", decision_note: null, outcome_note: "تمت الموافقة" },
    package: pkg,
    consent: { active: null, history: [], text_ar: "نص", version: "v1", withdrawal_notice: "" },
    history: [],
    can: { consent: false, withdraw: false, share: false, mfi_transitions: [], record_outcome: false },
    statusLabels: {},
};

const sectionByTitle = (t: string) => screen.getByRole("heading", { name: t }).closest("section") as HTMLElement;

describe("Referrals/Show package", () => {
    it("never renders <pre>, JSON or technical keys", () => {
        const { container } = render(<Show {...props} />);
        expect(container.querySelector("pre")).toBeNull();
        const text = container.textContent ?? "";
        expect(text).not.toMatch(/[{}]|":|\[\s*"/);
        for (const k of ["business_name", "enterprise_profile", "readiness_assessment", "public_id", "storage_path", "nested_key", "weird_field", "01ABC", "99"]) expect(text).not.toContain(k);
    });

    it("shows enterprise info with labels, formatted dates and hides empty fields", () => {
        render(<Show {...props} />);
        const s = sectionByTitle("معلومات المنشأة");
        expect(s.textContent).toContain("اسم المنشأة");
        expect(s.textContent).toContain("ورشة الياسمين");
        expect(s.textContent).toContain("2020");
        expect(s.textContent).not.toContain("2020-03-15");
        expect(s.textContent).not.toContain("حالة التسجيل");
    });

    it("shows the readiness result with score, band and mandatory badges", () => {
        render(<Show {...props} />);
        const b = within(sectionByTitle("نتيجة الجاهزية")).getByTestId("readiness-badges");
        expect(b.textContent).toContain("72.5");
        expect(b.textContent).toContain("متوسطة");
        expect(b.querySelector(".app-status--success")?.textContent).toContain("الشروط الإلزامية مستوفاة");
        expect(sectionByTitle("نتيجة الجاهزية").textContent).toContain("وليست موافقة");
    });

    it("formats money in the summary currency and lists compliance/documents", () => {
        render(<Show {...props} />);
        const fin = sectionByTitle("الملخص المالي").textContent ?? "";
        expect(fin).toContain("1,500,000");
        expect(fin).toContain("ل.س");
        expect(fin).not.toContain("العملة");
        const comp = sectionByTitle("حالة الامتثال");
        expect(comp.querySelector("table")).toBeTruthy();
        expect(comp.textContent).toContain("ترخيص");
        expect(sectionByTitle("المستندات").textContent).toContain("السجل التجاري");
    });

    it("puts unknown fields in «تفاصيل إضافية» with humanized labels", () => {
        render(<Show {...props} />);
        const s = sectionByTitle("تفاصيل إضافية");
        expect(s.textContent).toContain("late_payments");
        expect(s.textContent).toContain("معلومة إضافية");
        expect(s.querySelector(".app-status--success")).toBeTruthy();
        expect(humanizeKey("payment_date")).toBe("تاريخ");
        expect(humanizeKey("zzz")).toBe("معلومة إضافية");
    });

    it("shows notes in a notes card and hides empty ones", () => {
        render(<Show {...props} />);
        const n = sectionByTitle("الملاحظات");
        expect(n.textContent).toContain("أرسلوا كشف حساب");
        expect(n.textContent).toContain("تمت الموافقة");
        expect(n.textContent).not.toContain("القرار");
    });

    it("handles an empty package", () => {
        const { container } = render(<Show {...props} package={{}} />);
        expect(container.querySelector("pre")).toBeNull();
        expect(screen.getByText("لا توجد بيانات مشاركة بعد.")).toBeTruthy();
    });
});
