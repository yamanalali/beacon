import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
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

const a = { public_id: "A1", status: "finalized", score: "72.5", mandatory_conditions_met: true, rule_set: "قواعد 2026", created_at: "2026-10-01", finalized_at: "2026-10-02" };

describe("Readiness/Enterprise restyle", () => {
    it("uses the page header, section card and record cards", () => {
        const { container } = render(<Enterprise enterprise={enterprise} assessments={[a, { ...a, public_id: "A2", status: "draft", score: null, finalized_at: null, mandatory_conditions_met: null }]} canAssess hasPublishedRules />);
        expect(container.querySelector(".app-page-header h1")?.textContent).toBe("تقييم الجاهزية المالية");
        expect(container.querySelector(".app-section h2")?.textContent).toBe("التقييمات");
        const cards = container.querySelectorAll("a.app-mobile-record");
        expect(cards).toHaveLength(2);
        expect(cards[0].getAttribute("href")).toBe("/readiness-assessments/A1");
        expect(within(cards[0] as HTMLElement).getByText("نهائي")).toBeTruthy();
        expect(cards[0].textContent).toContain("72.5");
        expect(cards[0].textContent).toContain("مستوفاة");
        expect(within(cards[1] as HTMLElement).getByText("مسودة")).toBeTruthy();
        expect(cards[1].textContent).toContain("لم تُحسب النتيجة");
    });

    it("shows an empty state with a start action", () => {
        const { container } = render(<Enterprise enterprise={enterprise} assessments={[]} canAssess hasPublishedRules />);
        const empty = container.querySelector(".app-empty") as HTMLElement;
        expect(empty.textContent).toContain("لا توجد تقييمات بعد");
        fireEvent.click(within(empty).getByRole("button", { name: "تقييم جديد" }));
        expect(post).toHaveBeenCalledWith("/enterprises/E1/readiness");
    });

    it("disables starting without published rules and hides the empty-state action", () => {
        const { container } = render(<Enterprise enterprise={enterprise} assessments={[]} canAssess hasPublishedRules={false} />);
        expect((screen.getByRole("button", { name: /تقييم جديد/ }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByText("لا توجد قواعد معتمدة من بيكون بعد.")).toBeTruthy();
        expect(container.querySelector(".app-empty button")).toBeNull();
    });

    it("hides the toolbar for users who cannot assess", () => {
        const { container } = render(<Enterprise enterprise={enterprise} assessments={[]} canAssess={false} hasPublishedRules />);
        expect(container.querySelector(".app-page-toolbar")).toBeNull();
        expect(screen.queryByRole("button")).toBeNull();
    });
});