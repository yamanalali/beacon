import { describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { reload: vi.fn() } }));

import { criterionBody, pillarBody, mutate } from "./adminApi";
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

it.each([401, 419, 405, 500])("does not report HTTP %i as a successful save", async status => {
    const result = await mutate("POST", "/rules", {}, vi.fn().mockResolvedValue(new Response('{"message":"SQLSTATE secret"}', { status })));
    expect(result.ok).toBe(false);
    expect(result).not.toHaveProperty("message", "SQLSTATE secret");
});

it("treats a redirect to login as an expired session", async () => {
    const response = new Response("<html>Login</html>");
    Object.defineProperties(response, { redirected: { value: true }, url: { value: "https://beacon.example/login" } });
    expect(await mutate("POST", "/rules", {}, vi.fn().mockResolvedValue(response))).toMatchObject({ ok: false });
});
