import { describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { reload: vi.fn() } }));

import { criterionBody, pillarBody } from "./adminApi";
import { exportRuleSet } from "./scoring";
import { criteria, draft, pillars } from "./fixtures.test-data";

describe("pillar weight payload (#82)", () => {
    it("sends null for an empty pillar weight, numbers otherwise", () => {
        expect(pillarBody({ ...pillars[0], weight: "" }).weight).toBeNull();
        expect(pillarBody({ ...pillars[0], weight: null }).weight).toBeNull();
        expect(pillarBody({ ...pillars[0], weight: "2.5" }).weight).toBe(2.5);
        expect(pillarBody({ ...pillars[0], weight: 0 }).weight).toBe(0);
    });
    it("keeps criterion weight as entered (required, validated by the server)", () => {
        expect(criterionBody({ ...criteria[0], weight: "3" }).weight).toBe(3);
    });
    it("exports an empty pillar weight as null", () => {
        const exp = exportRuleSet({ ...draft, pillars: [{ ...pillars[0], weight: null }, pillars[1]] });
        expect(exp.pillars[0].weight).toBeNull();
        expect(exp.pillars[1].weight).toBe(1);
    });
});