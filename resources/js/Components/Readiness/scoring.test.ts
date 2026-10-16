import { describe, expect, it } from "vitest";
import { bandFor, colorVar, criterionPoints, evaluate, exportRuleSet, importRuleSet, isBlocked, reorder, validateBands, validateRuleSet, resolvePillarWeights } from "./scoring";
import { bands, criteria, draft } from "./fixtures.test-data";

const byCode = (c: string) => criteria.find((x) => x.code === c)!;

describe("criterionPoints", () => {
    it("scores each type on 0..1", () => {
        expect(criterionPoints(byCode("a1"), true)).toBe(1);
        expect(criterionPoints(byCode("a1"), false)).toBe(0);
        expect(criterionPoints(byCode("a2"), 12)).toBe(1);
        expect(criterionPoints(byCode("a2"), 3)).toBe(0);
        expect(criterionPoints(byCode("b1"), "y")).toBe(0.5);
        expect(criterionPoints(byCode("b1"), "zzz")).toBeNull();
        expect(criterionPoints(byCode("b2"), 50)).toBe(0.5);
        expect(criterionPoints(byCode("b2"), 250)).toBe(1); // capped
        expect(criterionPoints(byCode("c1"), 3)).toBe(0.5);
        expect(criterionPoints(byCode("c1"), 9)).toBeNull();
    });
    it("handles lower-is-better targets and missing values", () => {
        const lower = { ...byCode("b2"), config: { target: 10, direction: "lower", cap: 1 } } as typeof criteria[number];
        expect(criterionPoints(lower, 20)).toBe(0.5);
        expect(criterionPoints(lower, 5)).toBe(1);
        expect(criterionPoints(byCode("a1"), null)).toBeNull();
        expect(criterionPoints(byCode("a2"), "")).toBeNull();
    });
});

describe("evaluate", () => {
    it("weights criteria within pillars and pillars in the total", () => {
        const b = evaluate(draft, { a1: true, a2: 15, b1: "y", b2: 50 });
        expect(b.pillars.map((p) => [p.code, p.score])).toEqual([["A", 100], ["B", 50], ["_default", null]]);
        expect(b.score).toBeCloseTo(83.33, 2);
        expect(b.band?.label_ar).toBe("مرتفعة");
        expect(b.mandatory_conditions_met).toBe(true);
        expect(isBlocked(b)).toBe(false);
        expect(b.needs_review).toEqual(["c1"]);
        expect(b.missing).toEqual(["c1"]);
        const sum = b.pillars.flatMap((p) => p.criteria).reduce((s, c) => s + c.contribution, 0);
        expect(sum).toBeCloseTo(b.score!, 1);
        expect(b.pillars[2].criteria[0].outcome).toBe("needs_review");
    });
    it("applies the mandatory gate and pillar minimum as blocking", () => {
        const b = evaluate(draft, { a1: false, a2: 15, b1: "x", b2: 100, c1: 5 });
        expect(b.unmet_mandatory).toEqual(["a1"]);
        expect(b.mandatory_conditions_met).toBe(false);
        expect(b.blocking).toEqual([]); // A = 50, not below 50
        const c = evaluate(draft, { a1: false, a2: 2, b1: "x", b2: 100 });
        expect(c.pillars[0].score).toBe(0);
        expect(c.blocking).toEqual(["A"]);
        expect(isBlocked(c)).toBe(true);
    });
    it("follows the missing-data treatment per criterion", () => {
        const b = evaluate(draft, { a1: true, b1: "x" });
        // a2 missing -> counts as unmet; b2 missing -> excluded
        expect(b.pillars[0].score).toBe(50);
        expect(b.pillars[1].score).toBe(100);
        expect(b.unmet).toContain("a2");
        expect(b.missing).toEqual(["a2", "b2", "c1"]);
        expect(b.pillars[1].criteria[1]).toMatchObject({ points: null, outcome: "missing", contribution: 0 });
    });
    it("ranks improvements by potential gain", () => {
        const b = evaluate(draft, { a1: true, a2: 15, b1: "y", b2: 50 });
        expect(b.improvements[0].code).toBe("c1");
        expect(b.improvements.map((i) => i.code)).toEqual(expect.arrayContaining(["b1", "b2"]));
        expect(b.improvements.every((i) => i.potential_gain > 0)).toBe(true);
    });
    it("returns no score when nothing can be counted", () => {
        const b = evaluate({ ...draft, criteria: [byCode("b1")] }, {});
        expect(b.score).toBeNull();
        expect(b.band).toBeNull();
    });
});

describe("default pillar weight (backend #74)", () => {
    it("gives unset pillars the average of the set ones (implicit pillar included)", () => {
        expect(resolvePillarWeights([2, 1, null])).toEqual([2, 1, 1.5]);
        expect(resolvePillarWeights([0, 4, null])).toEqual([4, 4, 4]);
        expect(resolvePillarWeights([null, 0])).toEqual([1, 1]);
        const b = evaluate(draft, { a1: true, a2: 15, b1: "y", b2: 50, c1: 5 });
        expect(b.pillars.map((p) => p.weight)).toEqual([2, 1, 1.5]);
        expect(b.score).toBeCloseTo(400 / 4.5, 2);
    });
    it("uses weight 1 for all when no pillar has a weight", () => {
        const rs = { ...draft, pillars: draft.pillars.map((p) => ({ ...p, weight: "" })) };
        const b = evaluate(rs, { a1: true, a2: 15, b1: "y", b2: 50, c1: 5 });
        expect(b.pillars.map((p) => p.weight)).toEqual([1, 1, 1]);
        expect(b.score).toBeCloseTo(250 / 3, 2);
    });
    it("leaves pillars without criteria out of the average, like the server", () => {
        const rs = { ...draft, pillars: [...draft.pillars, { ...draft.pillars[1], id: 30, code: "E", weight: 9, sort_order: 2 }] };
        expect(evaluate(rs, {}).pillars.map((p) => p.code)).toEqual(["A", "B", "_default"]);
        expect(evaluate(rs, {}).pillars[2].weight).toBe(1.5);
    });
});

describe("bands and validation", () => {
    it("finds the band with inclusive upper end on the last band", () => {
        expect(bandFor(bands, 49.99)?.label_ar).toBe("منخفضة");
        expect(bandFor(bands, 50)?.label_ar).toBe("متوسطة");
        expect(bandFor(bands, 100)?.label_ar).toBe("مرتفعة");
    });
    it("accepts the fixture rule set", () => {
        expect(validateRuleSet(draft)).toEqual([]);
    });
    it("flags gaps, overlaps, unknown colours and zero weights", () => {
        const msgs = validateBands([{ ...bands[0], max: 40 }, { ...bands[1], min: 45, max: 90, color_token: "#ff0000" }, { ...bands[2], min: 85 }]).map((x) => x.message).join("|");
        expect(msgs).toMatch(/فجوة بين 40 و45/);
        expect(msgs).toMatch(/متداخلتان/);
        expect(msgs).toMatch(/ليس من ألوان النظام/);
        expect(validateBands([{ ...bands[1], min: 10, max: 90 }]).map((x) => x.message).join("|")).toMatch(/من 0 إلى 10.*|.*من 90 إلى 100/);
        const v = validateRuleSet({ ...draft, pillars: [{ ...draft.pillars[0], weight: 0 }, draft.pillars[1]], criteria: [{ ...criteria[0], weight: "" }, ...criteria.slice(1)] }).map((x) => x.message).join("|");
        expect(v).not.toMatch(/وزن المحور/); // 0 = equal default share
        expect(v).toMatch(/وزن المعيار «سجل تجاري»/);
        const neg = validateRuleSet({ ...draft, pillars: [{ ...draft.pillars[0], weight: -1 }, draft.pillars[1]] }).map((x) => x.message).join("|");
        expect(neg).toMatch(/وزن المحور «محور أ» لا يمكن أن يكون سالباً/);
    });
    it("blocks publish without bands with the server message, but not import", () => {
        expect(validateRuleSet({ ...draft, bands: [] }).map((x) => x.message)).toContain("لا يمكن نشر القواعد بدون نطاقات تصنيف.");
        expect(validateRuleSet({ ...draft, bands: [] }, false)).toEqual([]);
    });
    it("flags bad criterion configs", () => {
        const bad = [
            { ...byCode("a2"), config: { unit: "", bands: [{ min: 0, max: 10, points: 0 }, { min: 5, max: null, points: 2 }] } },
            { ...byCode("b1"), config: { options: [] } },
            { ...byCode("b2"), config: { target: 0, direction: "higher", cap: 1 } },
        ] as typeof criteria;
        const v = validateRuleSet({ ...draft, criteria: bad }).map((x) => x.message).join("|");
        expect(v).toMatch(/متداخلة/);
        expect(v).toMatch(/بين 0 و1/);
        expect(v).toMatch(/أضف خيارات/);
        expect(v).toMatch(/حدد هدفاً/);
    });
    it("maps colours to #13 tokens only", () => {
        expect(colorVar("success")).toBe("var(--app-success)");
        expect(colorVar("#123456")).toBe("var(--app-text-muted)");
    });
});

describe("JSON import/export", () => {
    it("round-trips into a new draft and validates it", () => {
        const json = JSON.stringify(exportRuleSet({ ...draft, status: "published" }));
        expect(json).not.toMatch(/"readiness_rule_set_id"/);
        const r = importRuleSet(json, { id: 9, version_number: 4 });
        expect(r.ok).toBe(true);
        if (!r.ok) return;
        expect(r.draft.status).toBe("draft");
        expect(r.issues).toEqual([]);
        expect(r.draft.criteria.find((c) => c.code === "b1")?.readiness_pillar_id).toBe(r.draft.pillars.find((p) => p.code === "B")?.id);
        expect(r.draft.criteria.find((c) => c.code === "c1")?.readiness_pillar_id).toBeNull();
        expect(evaluate(r.draft, { a1: true, a2: 15, b1: "y", b2: 50 }).score).toBeCloseTo(83.33, 2);
    });
    it("rejects bad files and reports publish issues on import", () => {
        expect(importRuleSet("{", { id: 1, version_number: 1 })).toMatchObject({ ok: false });
        expect(importRuleSet(JSON.stringify({ format: "other" }), { id: 1, version_number: 1 })).toMatchObject({ ok: false });
        const exp = exportRuleSet(draft);
        exp.bands = exp.bands.slice(0, 2);
        const r = importRuleSet(JSON.stringify(exp), { id: 1, version_number: 1 });
        expect(r.ok && r.issues.some((i) => /إلى 100 غير مغطى/.test(i.message))).toBe(true);
        const t = exportRuleSet(draft);
        (t.criteria[0] as { type: string }).type = "formula";
        expect(importRuleSet(JSON.stringify(t), { id: 1, version_number: 1 })).toMatchObject({ ok: false });
    });
});

describe("reorder", () => {
    it("moves and renumbers sort_order", () => {
        const r = reorder(draft.pillars, 1, 0);
        expect(r.map((p) => [p.code, p.sort_order])).toEqual([["B", 0], ["A", 1]]);
    });
});
