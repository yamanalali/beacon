import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

const { post, put } = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn() }));
vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children, className }: { href: string; children: ReactNode; className?: string }) => <a href={href} className={className}>{children}</a>,
    usePage: () => ({ props: { errors: {} } }),
    useForm: () => ({
        data: { file: null, title: "" },
        setData: vi.fn(),
        post: post,
        processing: false,
        progress: null,
        errors: {},
        reset: vi.fn(),
    }),
    router: { post, put },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));

afterEach(() => { cleanup(); post.mockClear(); put.mockClear(); });
const enterprise = { public_id: "E1", business_name: "ورشة الياسمين" };
import Enterprise from "./Enterprise";

const item = {
    requirement_public_id: "R1", category_label: "ضرائب",
    version: { version_number: 2, title_ar: "تسجيل ضريبي", guidance_ar: "قدّم الطلب", guidance_pending: false, deadline_note_ar: null },
    requires_document: false,
    status: "in_progress", status_label: "قيد التنفيذ", due_date: "2026-11-01", notes: null,
    missing_document: true, overdue: true, approaching: false, action_needed: true,
    can_upload: false, can_review: false, latest_submission: null,
};
const statuses = { in_progress: "قيد التنفيذ", completed: "مكتمل", not_started: "لم يبدأ" };

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

    it("shows SME upload and advisor review actions", () => {
        render(
            <Enterprise
                enterprise={enterprise}
                items={[{
                    ...item,
                    requires_document: true,
                    missing_document: true,
                    can_upload: true,
                    status: "not_started",
                    status_label: "لم يبدأ",
                }]}
                statuses={statuses}
                canUpdate={false}
                canSubmit
            />,
        );
        expect(screen.getByRole("button", { name: "رفع للمراجعة" })).toBeTruthy();

        cleanup();
        render(
            <Enterprise
                enterprise={enterprise}
                items={[{
                    ...item,
                    requires_document: true,
                    status: "awaiting_review",
                    status_label: "بانتظار المراجعة",
                    can_upload: false,
                    can_review: true,
                    missing_document: false,
                    latest_submission: {
                        id: 9,
                        original_name: "r.pdf",
                        title: "رخصة",
                        submitted_at: "2026-11-01T10:00:00+00:00",
                        review_status: "pending",
                        review_status_label: "بانتظار المراجعة",
                        review_note: null,
                        document_public_id: "D1",
                        document_url: "/documents/D1/download",
                    },
                }]}
                statuses={statuses}
                canUpdate
                canReview
            />,
        );
        expect(screen.getByRole("button", { name: "اعتماد" })).toBeTruthy();
        expect(screen.getByRole("button", { name: "رفض" })).toBeTruthy();
        expect(screen.getByRole("button", { name: "طلب وثيقة أخرى" })).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: "اعتماد" }));
        expect(post).toHaveBeenCalledWith(
            "/enterprises/E1/compliance/R1/submissions/9/review",
            { decision: "approve", review_note: null },
            expect.objectContaining({ preserveScroll: true }),
        );
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
