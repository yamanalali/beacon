import { describe, expect, it, vi } from "vitest";
import { handleInertiaConflict, headerValue } from "./inertiaConflict";

const loc = () => ({ assign: vi.fn(), reload: vi.fn() });

describe("handleInertiaConflict", () => {
    it("assigns X-Inertia-Location on 409 (case-insensitive headers)", () => {
        const l = loc();
        expect(handleInertiaConflict({ status: 409, headers: { "x-inertia-location": "/extractions/X1" } }, l)).toBe(true);
        expect(l.assign).toHaveBeenCalledWith("/extractions/X1");
        expect(l.reload).not.toHaveBeenCalled();
    });
    it("works with fetch Headers", () => {
        const l = loc();
        handleInertiaConflict({ status: 409, headers: new Headers({ "X-Inertia-Location": "http://app/x" }) }, l);
        expect(l.assign).toHaveBeenCalledWith("http://app/x");
    });
    it("reloads on 409 without the header", () => {
        const l = loc();
        expect(handleInertiaConflict({ status: 409, headers: {} }, l)).toBe(true);
        expect(l.reload).toHaveBeenCalled();
    });
    it("ignores other statuses", () => {
        const l = loc();
        expect(handleInertiaConflict({ status: 500, headers: { "X-Inertia-Location": "/x" } }, l)).toBe(false);
        expect(handleInertiaConflict(null, l)).toBe(false);
        expect(l.assign).not.toHaveBeenCalled();
        expect(headerValue(undefined, "a")).toBeNull();
    });
});