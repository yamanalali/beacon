import { cleanup, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { post: vi.fn() } }));

import type { ReadinessScoreSnapshot, ReadinessScoreSnapshotWithResult, ReadinessSnapshotResult } from "../../types/readiness-snapshot";
import { AssessmentResult, toView } from "./AssessmentResult";

afterEach(() => cleanup());

// Mock data for tests only, not business values.
const crit = (code: string, label_ar: string, points: number | null, outcome: string) => ({ code, label_ar, type: "boolean", value: null, points, weight: 1, contribution: points ? 10 : 0, outcome }) as never;
const pillar = (code: string, label_ar: string, score: number | null, criteria: never[], blocking = false) => ({ code, label_ar, weight: 1, score, min_score: null, blocking, contribution: 0, criteria });
const result: ReadinessSnapshotResult = {
    score: 61.5,
    band: { label_ar: "متوسطة", name: "متوسطة", color_token: "warning", min: 50, max: 80 },
    mandatory_conditions_met: false,
    blocking: ["B"],
    pillars: [pillar("A", "الإدارة", 80, [crit("a1", "سجل تجاري", 0, "not_met")]), pillar("B", "المالية", 30, [crit("b1", "قوائم مالية", null, "needs_review")], true), pillar("C", "السوق", 70, [])],
    breakdown: null,
    improvements: [{ code: "a1", label_ar: "سجل تجاري", potential_gain: 12 }],
    earned_weight: 2, counted_weight: 3,
    unmet_mandatory: ["a1"], needs_review: ["b1"], missing: ["b1"], unmet: ["a1"],
};
const base: ReadinessScoreSnapshot = { id: 7, score: "61.5", band: "متوسطة", band_detail: { name: "متوسطة", label_ar: "متوسطة", color_token: "warning" }, mandatory_conditions_met: false, reason: "initial", reason_note: null, rule_set_version: 3, config_hash: "h", is_current: true, computed_at: "2026-10-16 12:00", computed_by: "مستشار" };
const snapshot: ReadinessScoreSnapshotWithResult = { ...base, result };

describe("AssessmentResult reads the result prop", () => {
    it("renders score, band colour from color_token, radar, bars, blocking, review and improvements", () => {
        render(<AssessmentResult result={result} snapshot={snapshot} snapshotHistory={[base]} />);
        expect(screen.getByTestId("result-score").textContent).toContain("61.5");
        expect(screen.getAllByText("متوسطة")[0].closest(".app-status")?.className).toMatch(/warning/);
        expect(screen.getByTestId("radar")).toBeTruthy();
        expect(screen.getAllByTestId("pillar-row")).toHaveLength(3);
        const blocking = screen.getByTestId("blocking").textContent ?? "";
        expect(blocking).toContain("سجل تجاري");
        expect(blocking).toContain("المالية");
        expect(screen.getByTestId("needs-review").textContent).toContain("قوائم مالية");
        expect(screen.getByTestId("improvements").textContent).toContain("+12");
        expect(within(screen.getByTestId("snapshot-history")).getByText("متوسطة").closest(".app-status")?.className).toMatch(/warning/);
    });

    it("falls back to snapshot.result when the top-level result is absent", () => {
        render(<AssessmentResult snapshot={snapshot} />);
        expect(screen.getAllByTestId("pillar-row")).toHaveLength(3);
    });

    it("uses breakdown when pillars is null", () => {
        const v = toView({ ...result, pillars: null, breakdown: result.pillars });
        expect(v?.pillars.map((p) => p.code)).toEqual(["A", "B", "C"]);
    });

    it("is null-safe for old snapshots (result null, v2 keys null)", () => {
        const old: ReadinessScoreSnapshotWithResult = { ...base, band_detail: null, band: "قديمة", result: null };
        render(<AssessmentResult result={null} snapshot={old} snapshotHistory={[{ ...old }]} />);
        expect(screen.getByTestId("result-score").textContent).toContain("61.5");
        expect(screen.getAllByText("قديمة")[0].closest(".app-status")?.className).toMatch(/neutral/);
        expect(screen.getByTestId("no-breakdown")).toBeTruthy();
        expect(screen.queryByTestId("radar")).toBeNull();
    });

    it("handles a v1 result with null pillars/blocking/improvements", () => {
        const v1: ReadinessSnapshotResult = { ...result, band: null, blocking: null, pillars: null, breakdown: null, improvements: null, unmet_mandatory: [], needs_review: [], missing: [], unmet: [], mandatory_conditions_met: true };
        render(<AssessmentResult result={v1} snapshot={{ ...base, band_detail: null, band: null, result: v1 }} />);
        expect(screen.getByText("بلا شريحة")).toBeTruthy();
        expect(screen.queryByTestId("blocking")).toBeNull();
        expect(screen.queryByTestId("improvements")).toBeNull();
        expect(screen.getByText("الشروط الإلزامية مستوفاة")).toBeTruthy();
    });
});
