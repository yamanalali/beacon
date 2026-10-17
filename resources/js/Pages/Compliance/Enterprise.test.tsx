import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

const { post, put } = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn() }));
vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children, className }: { href: string; children: ReactNode; className?: string }) => <a href={href} className={className}>{children}</a>,
    usePage: () => ({ props: { errors: {} } }),
    router: { post, put },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));

afterEach(() => { cleanup(); post.mockClear(); put.mockClear(); });
const enterprise = { public_id: "E1", business_name: "ورشة الياسمين" };
import Enterprise from "./Enterprise";

const item = {
    requirement_public_id: "R1", category_label: "ضرائب",
    version: { version_number: 2, title_ar: "تسجيل ضريبي", guidance_ar: "قدّم الطلب", guidance_pending: false, deadline_note_ar: null },
    status: "pending", status_label: "قيد التنفيذ", due_date: "2026-11-01", notes: null,
    missing_document: true, overdue: true, approaching: false, action_needed: true,
};
const statuses = { pending: "قيد التنفيذ", completed: "مكتمل" };

describe("Compliance/Enterprise restyle", () => {
    it("renders items as record cards inside a section card with a summary", () => {
        const { container } = render(<Enterprise enterprise={enterprise} items={[item, { ...item, requirement_public_id: "R2", status: "completed", status_label: "مكتمل", overdue: false, action_needed: false, missing_document: false }]} statuses={statuses} canUpdate={false} />);
        expect(container.querySelector(".app-page-header h1")?.textContent).toBe("الامتثال");
        expect(container.querySelector(".app-section h2")?.textContent).toBe("المتطلبات");
        const rows = screen.getAllByTestId("compliance-item");
        expect(rows).toHaveLength(2);
        expect(rows[0].textContent).toContain("وثيقة مطلوبة لم تُستكمل");
        expect(rows[0].textContent).toContain("(متأخر)");
        expect(rows[0].querySelector(".app-status--danger")).toBeTruthy();
        expect(rows[1].querySelector(".app-status--success")).toBeTruthy();
        const summary = screen.getByTestId("compliance-summary");
        expect(summary.textContent).toContain("1 من 2 مكتمل");
        expect(summary.textContent).toContain("1 يحتاج إجراء");
        expect(screen.queryByRole("button", { name: "حفظ" })).toBeNull();
    });

    it("has labelled controls and saves the edited status", () => {
        render(<Enterprise enterprise={enterprise} items={[item]} statuses={statuses} canUpdate />);
        fireEvent.change(screen.getByLabelText("الحالة"), { target: { value: "completed" } });
        fireEvent.change(screen.getByLabelText("الموعد"), { target: { value: "" } });
        fireEvent.click(screen.getByRole("button", { name: "حفظ" }));
        expect(put).toHaveBeenCalledWith("/enterprises/E1/compliance/R1", { status: "completed", due_date: null, version_number: 2 }, expect.objectContaining({ preserveScroll: true }));
    });

    it("warns when an older completion needs review for the current version", () => {
        render(<Enterprise enterprise={enterprise} items={[{ ...item, status: "not_started", status_label: "لم يبدأ", needs_version_review: true, recorded_version_number: 1, recorded_status_label: "مكتمل" }]} statuses={statuses} canUpdate={false} />);
        expect(screen.getByRole("status").textContent).toContain("صدر إصدار جديد يحتاج مراجعة");
        expect(screen.getByTestId("compliance-summary").textContent).toContain("0 من 1 مكتمل");
    });

    it("shows the empty state and no summary when there are no requirements", () => {
        const { container } = render(<Enterprise enterprise={enterprise} items={[]} statuses={statuses} canUpdate />);
        expect(container.querySelector(".app-empty")?.textContent).toContain("لا توجد متطلبات منشورة بعد");
        expect(screen.queryByTestId("compliance-summary")).toBeNull();
    });
});