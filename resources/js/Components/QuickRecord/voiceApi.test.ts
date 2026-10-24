import { describe, expect, it, vi } from "vitest";
import { sendVoice, sendTranscript } from "./voiceApi";

const res = (status: number, body: unknown = {}) => new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });
const wav = new Blob(["x"], { type: "audio/wav" });

describe("sendVoice", () => {
    it("posts multipart audio as JSON with the XSRF header", async () => {
        document.cookie = "XSRF-TOKEN=abc%3D";
        const f = vi.fn().mockResolvedValue(res(200, { draft: {}, confirm: { url: "/x" }, options: { types: [], currencies: [], categories: {}, payment_statuses: [] } }));
        const r = await sendVoice("/ai/enterprises/E/voice-drafts", wav, f);
        expect(r.ok).toBe(true);
        const [url, init] = f.mock.calls[0];
        expect(url).toBe("/ai/enterprises/E/voice-drafts");
        expect(init.headers.Accept).toBe("application/json");
        expect(init.headers["X-XSRF-TOKEN"]).toBe("abc=");
        expect((init.body as FormData).get("audio")).toBeInstanceOf(Blob);
    });
    it.each([404, 503])("treats %i as voice unavailable", async (s) => {
        const r = await sendVoice("/v", wav, vi.fn().mockResolvedValue(res(s)));
        expect(r).toMatchObject({ ok: false, kind: "unavailable" });
    });
    it("surfaces 422 validation and network errors", async () => {
        expect(await sendVoice("/v", wav, vi.fn().mockResolvedValue(res(422, { errors: { audio: ["الملف كبير"] } })))).toMatchObject({ kind: "invalid", message: "الملف كبير" });
        expect(await sendVoice("/v", wav, vi.fn().mockRejectedValue(new Error("x")))).toMatchObject({ kind: "error" });
    });
    it("full-loads X-Inertia-Location on 409", async () => {
        const assign = vi.fn();
        const orig = window.location;
        Object.defineProperty(window, "location", { configurable: true, value: { ...orig, assign, reload: vi.fn() } });
        const r = new Response(null, { status: 409, headers: { "X-Inertia-Location": "/extractions/X1" } });
        await sendVoice("/v", wav, vi.fn().mockResolvedValue(r));
        expect(assign).toHaveBeenCalledWith("/extractions/X1");
        Object.defineProperty(window, "location", { configurable: true, value: orig });
    });
});

it.each([sendVoice, sendTranscript])("handles HTML and incomplete server responses without crashing", async send => {
    for (const response of [new Response("<html>Login</html>", { status: 200 }), res(200, {}), res(200, null)]) {
        const result = send === sendVoice
            ? await sendVoice("/v", wav, vi.fn().mockResolvedValue(response))
            : await sendTranscript("/v", "دفعت", vi.fn().mockResolvedValue(response));
        expect(result).toMatchObject({ ok: false, kind: "error" });
    }
});
