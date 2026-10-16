import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { post: vi.fn() } }));
vi.mock("./useWavRecorder", () => ({ MAX_SECONDS: 60, useWavRecorder: () => ({ state: "idle", seconds: 0, start: vi.fn(), stop: vi.fn(), cancel: vi.fn() }) }));

import { VoiceRecorder } from "./VoiceRecorder";
import { SPEECH_LANG, SPEECH_PREF_KEY, getSpeechRecognition, type SpeechEvent, type SpeechRecognitionLike } from "./browserSpeech";
import { sendTranscript, type VoiceResult } from "./voiceApi";

class MockRecognition implements SpeechRecognitionLike {
    static last: MockRecognition | null = null;
    lang = ""; continuous = false; interimResults = false;
    onresult: SpeechRecognitionLike["onresult"] = null;
    onerror: SpeechRecognitionLike["onerror"] = null;
    onend: SpeechRecognitionLike["onend"] = null;
    start = vi.fn();
    stop = vi.fn(() => this.onend?.());
    abort = vi.fn();
    constructor() { MockRecognition.last = this; }
    emit(parts: [string, boolean][]) {
        const results = Object.assign(parts.map(([t, f]) => Object.assign([{ transcript: t }], { isFinal: f })), {});
        this.onresult?.({ resultIndex: 0, results } as unknown as SpeechEvent);
    }
}

const draftRes: VoiceResult = {
    ok: true,
    data: {
        draft: { suggested_date: null, suggested_amount: "3000", suggested_currency: "SYP", suggested_counterparty: null, raw_text: "دفعت 3000 إيجار" },
        type: "expense", category_suggestion: null, confirm: { url: "/confirm" },
        options: { types: [{ value: "expense", label: "مصروف" }], categories: { expense: [] }, currencies: [{ value: "SYP", label: "ل.س" }], payment_statuses: [{ value: "paid", label: "مدفوع" }] },
    } as never,
};

beforeEach(() => { localStorage.clear(); MockRecognition.last = null; });
afterEach(cleanup);

const renderRec = (props: Partial<Parameters<typeof VoiceRecorder>[0]> = {}) =>
    render(<VoiceRecorder voiceUrl="/voice" manualUrl="/manual" speechCtor={MockRecognition} sendText={vi.fn().mockResolvedValue(draftRes)} {...props} />);

describe("browser speech toggle", () => {
    it("is hidden when the API is unsupported and the WAV recorder stays", () => {
        renderRec({ speechCtor: null });
        expect(screen.queryByTestId("speech-toggle")).toBeNull();
        expect(screen.getByRole("button", { name: "ابدأ التسجيل" })).toBeTruthy();
    });

    it("detects webkitSpeechRecognition", () => {
        expect(getSpeechRecognition({ webkitSpeechRecognition: MockRecognition })).toBe(MockRecognition);
        expect(getSpeechRecognition({})).toBeNull();
    });

    it("is off by default and needs the Google notice to be accepted", () => {
        renderRec();
        const sw = screen.getByRole("switch") as HTMLInputElement;
        expect(sw.checked).toBe(false);
        expect(screen.getByRole("button", { name: "ابدأ التسجيل" })).toBeTruthy();
        fireEvent.click(sw);
        expect(screen.getByTestId("speech-notice").textContent).toContain("Google");
        fireEvent.click(screen.getByRole("button", { name: "إلغاء" }));
        expect(localStorage.getItem(SPEECH_PREF_KEY)).toBeNull();
        expect(screen.queryByTestId("browser-speech")).toBeNull();
        fireEvent.click(screen.getByRole("switch"));
        fireEvent.click(screen.getByRole("button", { name: "أوافق، فعّل" }));
        expect(localStorage.getItem(SPEECH_PREF_KEY)).toBe("on");
        expect(screen.getByTestId("browser-speech")).toBeTruthy();
        fireEvent.click(screen.getByRole("switch"));
        expect(localStorage.getItem(SPEECH_PREF_KEY)).toBeNull();
        expect(screen.getByRole("button", { name: "ابدأ التسجيل" })).toBeTruthy();
    });

    it("remembers the choice", () => {
        localStorage.setItem(SPEECH_PREF_KEY, "on");
        renderRec();
        expect(screen.getByTestId("browser-speech")).toBeTruthy();
    });
});

describe("browser speech capture", () => {
    beforeEach(() => localStorage.setItem(SPEECH_PREF_KEY, "on"));

    it("shows live interim text in an editable textarea and submits it to the voice flow", async () => {
        const sendText = vi.fn().mockResolvedValue(draftRes);
        renderRec({ sendText });
        fireEvent.click(screen.getByRole("button", { name: "ابدأ الاستماع" }));
        const r = MockRecognition.last!;
        expect(r.lang).toBe(SPEECH_LANG);
        expect(r.interimResults).toBe(true);
        expect(r.start).toHaveBeenCalled();
        act(() => r.emit([["دفعت ثلاثة آلاف", true], ["إيجار", false]]));
        const ta = screen.getByLabelText("النص") as HTMLTextAreaElement;
        expect(ta.value).toBe("دفعت ثلاثة آلاف إيجار");
        expect(screen.getByTestId("speech-interim").textContent).toContain("إيجار");
        expect((screen.getByRole("button", { name: "تحويل إلى مسودة" }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole("button", { name: "إيقاف الاستماع" }));
        expect(r.stop).toHaveBeenCalled();
        fireEvent.change(screen.getByLabelText("النص"), { target: { value: "دفعت 3000 إيجار المحل" } });
        fireEvent.click(screen.getByRole("button", { name: "تحويل إلى مسودة" }));
        expect(sendText).toHaveBeenCalledWith("/voice", "دفعت 3000 إيجار المحل");
        await waitFor(() => expect(screen.getByText("ما فهمناه من تسجيلك:")).toBeTruthy());
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("3000");
    });

    it("reports a denied microphone", () => {
        renderRec();
        fireEvent.click(screen.getByRole("button", { name: "ابدأ الاستماع" }));
        act(() => MockRecognition.last!.onerror?.({ error: "not-allowed" }));
        expect(screen.getByRole("alert").textContent).toContain("الميكروفون");
    });

    it("shows the server message when text is not accepted yet", async () => {
        renderRec({ sendText: vi.fn().mockResolvedValue({ ok: false, kind: "unsupported_text", message: "الخادم لا يقبل النص" }) });
        fireEvent.click(screen.getByRole("button", { name: "ابدأ الاستماع" }));
        act(() => MockRecognition.last!.emit([["نص", true]]));
        fireEvent.click(screen.getByRole("button", { name: "إيقاف الاستماع" }));
        fireEvent.click(screen.getByRole("button", { name: "تحويل إلى مسودة" }));
        await waitFor(() => expect(screen.getByRole("alert").textContent).toContain("الخادم لا يقبل النص"));
        expect(screen.getByRole("button", { name: "حاول مرة أخرى" })).toBeTruthy();
    });
});

describe("sendTranscript", () => {
    const json = (status: number, body: unknown) => Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));

    it("posts the transcript as form data", async () => {
        const f = vi.fn().mockReturnValue(json(200, { draft: {} }));
        const r = await sendTranscript("/voice", "دفعت 3000", f as unknown as typeof fetch);
        expect(r.ok).toBe(true);
        const body = f.mock.calls[0][1].body as FormData;
        expect(body.get("transcript")).toBe("دفعت 3000");
        expect(body.get("audio")).toBeNull();
    });

    it("maps a 422 on audio to unsupported_text (backend lacks a transcript param)", async () => {
        const f = vi.fn().mockReturnValue(json(422, { errors: { audio: ["مطلوب"] } }));
        await expect(sendTranscript("/voice", "x", f as unknown as typeof fetch)).resolves.toMatchObject({ ok: false, kind: "unsupported_text" });
    });
});
