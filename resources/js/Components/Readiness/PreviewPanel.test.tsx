import { act, cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock("@inertiajs/react", () => ({ router: { post } }));

import { PreviewPanel } from "./PreviewPanel";
import { AssessmentResult } from "./AssessmentResult";
import { runPreview } from "./previewApi";
import { evaluate } from "./scoring";
import { draft } from "./fixtures.test-data";
import type { SnapshotWithBreakdown } from "./types";

afterEach(() => { cleanup(); post.mockClear(); vi.restoreAllMocks(); });

describe("preview", () => {
    it("uses the server preview when it answers", async () => {
        const fake = evaluate(draft, { a1: true });
        const fetcher = vi.fn().mockResolvedValue({ ...fake, score: 42 });
        await expect(runPreview(draft, { a1: true }, fetcher)).resolves.toMatchObject({ source: "server", breakdown: { score: 42 } });
        expect(fetcher).toHaveBeenCalledWith(draft, { a1: true });
    });

    it("falls back to the client calculation when the endpoint is missing", async () => {
        const fetcher = vi.fn().mockRejectedValue(new Error("preview 404"));
        const r = await runPreview(draft, { a1: true, a2: 15, b1: "y", b2: 50 }, fetcher);
        if (!("breakdown" in r)) throw new Error("expected a breakdown");
        expect(r.source).toBe("client");
        expect(r.breakdown.score).toBeCloseTo(83.33, 2);
    });

    it("recomputes live from test answers", async () => {
        render(<PreviewPanel ruleSet={draft} fetcher={null} delay={0} />);
        await waitFor(() => expect(screen.getByTestId("result-score")).toBeTruthy());
        expect(screen.getByTestId("preview-source")).toBeTruthy();
        fireEvent.change(screen.getByLabelText("سجل تجاري *"), { target: { value: "true" } });
        fireEvent.change(screen.getByLabelText("أشهر السجلات"), { target: { value: "15" } });
        fireEvent.change(screen.getByLabelText("نوع الحسابات"), { target: { value: "y" } });
        fireEvent.change(screen.getByLabelText("نسبة التحصيل"), { target: { value: "50" } });
        await waitFor(() => expect(screen.getByTestId("result-score").textContent).toContain("83.33"));
        expect(screen.getByText("مرتفعة")).toBeTruthy();
        fireEvent.change(screen.getByLabelText("سجل تجاري *"), { target: { value: "false" } });
        await waitFor(() => expect(screen.getByTestId("blocking").textContent).toContain("سجل تجاري"));
    });
});

describe("AssessmentResult", () => {
    const b = evaluate(draft, { a1: true, a2: 15, b1: "y", b2: 50 });

    it("shows radar, pillar/criterion bars, band, missing and improvements", () => {
        render(<AssessmentResult breakdown={b} />);
        expect(screen.getByTestId("radar")).toBeTruthy();
        expect(screen.getAllByTestId("pillar-row")).toHaveLength(3);
        expect(screen.getAllByTestId("criterion-bar")).toHaveLength(5);
        expect(screen.getByText("مرتفعة").closest(".app-status--success")).toBeTruthy();
        expect(screen.getByTestId("missing-items").textContent).toContain("تقييم المستشار");
        expect(screen.getByTestId("improvements").textContent).toContain("استكمال بيانات «تقييم المستشار»");
        expect(screen.queryByTestId("recalculate")).toBeNull();
    });

    it("skips the radar for fewer than 3 pillars", () => {
        render(<AssessmentResult breakdown={{ ...b, pillars: b.pillars.slice(0, 2) }} />);
        expect(screen.queryByTestId("radar")).toBeNull();
    });

    const snap: SnapshotWithBreakdown = { id: 7, score: "61.5", band_detail: null, band: "متوسطة", mandatory_conditions_met: true, reason: "recalculation", reason_note: "تحديث القواعد", rule_set_version: 3, config_hash: "h", is_current: true, computed_at: "2026-10-16 12:00", computed_by: "مستشار" };

    it("reads score and band from the frozen snapshot, not the live breakdown", () => {
        render(<AssessmentResult breakdown={b} snapshot={snap} snapshotHistory={[snap, { ...snap, id: 6, score: 55, reason: "initial", reason_note: null, is_current: false }]} />);
        expect(screen.getByTestId("result-score").textContent).toContain("61.5");
        expect(screen.getAllByText("متوسطة")[0].closest(".app-status")).toBeTruthy();
        expect(screen.getByText(/تفاصيل المحاور غير محفوظة/)).toBeTruthy();
        const hist = screen.getByTestId("snapshot-history");
        expect(within(hist).getAllByRole("listitem")).toHaveLength(2);
        expect(hist.textContent).toContain("أول احتساب");
        expect(hist.textContent).toContain("الحالية");
        expect(screen.queryByTestId("recalculate")).toBeNull();
    });

    it("recalculates with an optional reason after confirmation", () => {
        render(<AssessmentResult snapshot={snap} canRecalculate assessmentId="AS1" />);
        fireEvent.click(screen.getByRole("button", { name: /إعادة الاحتساب/ }));
        expect(post).not.toHaveBeenCalled();
        fireEvent.change(screen.getByLabelText("السبب (اختياري)"), { target: { value: "  قواعد جديدة " } });
        act(() => { fireEvent.click(screen.getByRole("button", { name: "تأكيد إعادة الاحتساب" })); });
        expect(post).toHaveBeenCalledWith("/readiness-assessments/AS1/recalculate", { reason: "قواعد جديدة" }, expect.objectContaining({ preserveScroll: true }));
    });

    it("sends a null reason when left empty and can be cancelled", () => {
        render(<AssessmentResult snapshot={snap} canRecalculate assessmentId="AS1" />);
        fireEvent.click(screen.getByRole("button", { name: /إعادة الاحتساب/ }));
        fireEvent.click(screen.getByRole("button", { name: "إلغاء" }));
        expect(screen.queryByLabelText("السبب (اختياري)")).toBeNull();
        fireEvent.click(screen.getByRole("button", { name: /إعادة الاحتساب/ }));
        fireEvent.click(screen.getByRole("button", { name: "تأكيد إعادة الاحتساب" }));
        expect(post).toHaveBeenCalledWith("/readiness-assessments/AS1/recalculate", { reason: null }, expect.anything());
    });
});
