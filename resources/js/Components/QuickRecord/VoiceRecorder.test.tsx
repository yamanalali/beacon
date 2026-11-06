import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const routerPost = vi.fn();
vi.mock("@inertiajs/react", () => ({ router: { post: (...a: unknown[]) => routerPost(...a) } }));

import { VoiceRecorder } from "./VoiceRecorder";
import type { SpeechEvent, SpeechRecognitionLike } from "./browserSpeech";
import type { VoiceDraftResponse, sendTranscript } from "./voiceApi";
import { localToday } from "../Transactions/ReviewForm";

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

const response: VoiceDraftResponse = {
    draft: { suggested_date: "2026-10-16", suggested_amount: "50000", suggested_currency: "SYP", suggested_counterparty: null, suggested_transaction_type: "expense", needs_review: true, raw_text: "دفعت خمسين ألف إيجار" },
    type: "expense",
    transaction_type_options: [{ value: "expense", label: "مصروف" }, { value: "income", label: "دخل" }],
    category_suggestion: null,
    options: { types: [{ value: "expense", label: "مصروف" }], payment_statuses: [{ value: "paid", label: "مدفوع" }], currencies: [{ value: "SYP", label: "ل.س" }], default_currency: "SYP", categories: { expense: [] } },
    confirm: { url: "/enterprises/E1/transactions" },
};

afterEach(() => { cleanup(); routerPost.mockReset(); MockRecognition.last = null; });

async function speakAndSubmit(sendText: typeof sendTranscript, text = "دفعت خمسين ألف إيجار") {
    render(<VoiceRecorder voiceUrl="/v" manualUrl="/manual" speechCtor={MockRecognition} sendText={sendText} />);
    fireEvent.click(screen.getByRole("button", { name: "ابدأ الاستماع" }));
    act(() => MockRecognition.last!.emit([[text, true]]));
    fireEvent.click(screen.getByRole("button", { name: "إيقاف الاستماع" }));
    await act(async () => { fireEvent.click(screen.getByRole("button", { name: "تحويل إلى مسودة" })); });
}

describe("VoiceRecorder", () => {
    it("always uses browser speech with no toggle", () => {
        render(<VoiceRecorder voiceUrl="/v" manualUrl="/manual" speechCtor={MockRecognition} sendText={vi.fn()} />);
        expect(screen.queryByTestId("speech-toggle")).toBeNull();
        expect(screen.queryByRole("switch")).toBeNull();
        expect(screen.getByTestId("browser-speech")).toBeTruthy();
        expect(screen.getByRole("button", { name: "ابدأ الاستماع" })).toBeTruthy();
    });

    it("shows unsupported + manual when SpeechRecognition is missing", () => {
        render(<VoiceRecorder voiceUrl="/v" manualUrl="/manual" speechCtor={null} sendText={vi.fn()} />);
        expect(screen.getByRole("alert").textContent).toContain("لا يدعم التعرّف على الكلام");
        expect(screen.getByRole("link", { name: "إدخال يدوي" }).getAttribute("href")).toBe("/manual");
        expect(screen.queryByTestId("browser-speech")).toBeNull();
    });

    it("shows the draft in the review form and confirms to confirm.url", async () => {
        await speakAndSubmit(vi.fn().mockResolvedValue({ ok: true, data: response }));
        expect(screen.getByText("دفعت خمسين ألف إيجار")).toBeTruthy();
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("50000");
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(routerPost).toHaveBeenCalledWith("/enterprises/E1/transactions", expect.objectContaining({ amount: "50000", type: "expense" }), expect.anything());
    });

    it("reject discards the draft without any request", async () => {
        await speakAndSubmit(vi.fn().mockResolvedValue({ ok: true, data: response }));
        fireEvent.click(screen.getByRole("button", { name: "تجاهل" }));
        expect(screen.queryByLabelText("المبلغ")).toBeNull();
        expect(routerPost).not.toHaveBeenCalled();
    });

    it("falls back to manual entry when voice is unavailable", async () => {
        await speakAndSubmit(vi.fn().mockResolvedValue({ ok: false, kind: "unavailable", message: "التسجيل الصوتي غير متاح حالياً." }));
        expect(screen.getByRole("alert").textContent).toContain("غير متاح");
        expect(screen.getByRole("link", { name: "إدخال يدوي" }).getAttribute("href")).toBe("/manual");
        expect(screen.getByRole("button", { name: "حاول مرة أخرى" })).toBeTruthy();
    });

    it("does not default unread currency, defaults missing date to today, and prefills suggested type + category", async () => {
        const res2 = {
            ...response,
            draft: { ...response.draft, suggested_currency: null, suggested_date: null, suggested_transaction_type: "income" },
            transaction_type_options: [{ value: "expense", label: "مصروف" }, { value: "income", label: "دخل" }],
            category_suggestion: { category: "rent", label: "إيجار", needs_review: false },
            options: { ...response.options, categories: { expense: [], income: [{ value: "rent", label: "إيجار" }] } },
        } as VoiceDraftResponse;
        await speakAndSubmit(vi.fn().mockResolvedValue({ ok: true, data: res2 }));
        expect((screen.getByLabelText(/العملة/) as HTMLSelectElement).value).toBe("");
        expect((screen.getByLabelText(/التاريخ/) as HTMLInputElement).value).toBe(localToday());
        expect(screen.getByTestId("date-default-hint").textContent).toBe("ما انذكر تاريخ، انحط اليوم");
        expect((screen.getByLabelText("التصنيف") as HTMLSelectElement).value).toBe("rent");
        expect((screen.getByLabelText("دخل") as HTMLInputElement).checked).toBe(true);
        expect(screen.getAllByText("مقترح").length).toBeGreaterThanOrEqual(2);
        const confirm = screen.getByRole("button", { name: /تأكيد وحفظ/ }) as HTMLButtonElement;
        expect(confirm.disabled).toBe(true);
        fireEvent.click(confirm);
        expect(routerPost).not.toHaveBeenCalled();
    });

    it("Yaman's rent sentence: backend expense type is checked with مقترح, date = today + hint", async () => {
        const res3 = { ...response, draft: { ...response.draft, source: "voice", suggested_date: null, suggested_amount: "3000", suggested_transaction_type: "expense", raw_text: "دفعت ايجار ثلاثه شهور اجار بيت هذا كل شهر هو 1000 ليره" } } as VoiceDraftResponse;
        await speakAndSubmit(vi.fn().mockResolvedValue({ ok: true, data: res3 }), "دفعت ايجار ثلاثه شهور");
        expect((screen.getByLabelText("مصروف") as HTMLInputElement).checked).toBe(true);
        const legend = screen.getByText("نوع العملية").closest("fieldset")!;
        expect(legend.textContent).toContain("مقترح");
        expect((screen.getByLabelText(/التاريخ/) as HTMLInputElement).value).toBe(localToday());
        expect(screen.getByTestId("date-default-hint")).toBeTruthy();
        fireEvent.change(screen.getByLabelText(/التاريخ/), { target: { value: "2026-01-01" } });
        expect(screen.queryByTestId("date-default-hint")).toBeNull();
    });

    it("keeps a spoken date (no default, no hint)", async () => {
        await speakAndSubmit(vi.fn().mockResolvedValue({ ok: true, data: response }));
        expect((screen.getByLabelText(/التاريخ/) as HTMLInputElement).value).toBe("2026-10-16");
        expect(screen.queryByTestId("date-default-hint")).toBeNull();
    });
});
