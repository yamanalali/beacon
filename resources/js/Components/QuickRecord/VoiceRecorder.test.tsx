import { act, cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const routerPost = vi.fn();
vi.mock("@inertiajs/react", () => ({ router: { post: (...a: unknown[]) => routerPost(...a) } }));
const rec = { state: "idle", seconds: 0, start: vi.fn(), stop: vi.fn(), cancel: vi.fn() };
vi.mock("./useWavRecorder", () => ({ MAX_SECONDS: 60, useWavRecorder: () => rec }));

import { VoiceRecorder } from "./VoiceRecorder";
import type { VoiceDraftResponse } from "./voiceApi";

const response: VoiceDraftResponse = {
    draft: { suggested_date: "2026-10-16", suggested_amount: "50000", suggested_currency: "SYP", suggested_counterparty: null, suggested_transaction_type: "expense", needs_review: true, raw_text: "دفعت خمسين ألف إيجار" },
    type: "expense",
    transaction_type_options: [{ value: "expense", label: "مصروف" }, { value: "income", label: "دخل" }],
    category_suggestion: null,
    options: { types: [{ value: "expense", label: "مصروف" }], payment_statuses: [{ value: "paid", label: "مدفوع" }], currencies: [{ value: "SYP", label: "ل.س" }], default_currency: "SYP", categories: { expense: [] } },
    confirm: { url: "/enterprises/E1/transactions" },
};
afterEach(() => { cleanup(); routerPost.mockReset(); });

async function recordAndStop(send: ReturnType<typeof vi.fn>) {
    rec.state = "recording";
    rec.stop.mockResolvedValue(new Blob(["x"], { type: "audio/wav" }));
    render(<VoiceRecorder voiceUrl="/v" manualUrl="/manual" send={send} />);
    await act(async () => { fireEvent.click(screen.getByRole("button", { name: "إيقاف التسجيل وإرساله" })); });
}

describe("VoiceRecorder", () => {
    it("shows the draft in the review form and confirms to confirm.url", async () => {
        await recordAndStop(vi.fn().mockResolvedValue({ ok: true, data: response }));
        expect(screen.getByText("دفعت خمسين ألف إيجار")).toBeTruthy();
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("50000");
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(routerPost).toHaveBeenCalledWith("/enterprises/E1/transactions", expect.objectContaining({ amount: "50000", type: "expense" }), expect.anything());
    });

    it("reject discards the draft without any request", async () => {
        await recordAndStop(vi.fn().mockResolvedValue({ ok: true, data: response }));
        fireEvent.click(screen.getByRole("button", { name: "تجاهل" }));
        expect(screen.queryByLabelText("المبلغ")).toBeNull();
        expect(routerPost).not.toHaveBeenCalled();
    });

    it("falls back to manual entry when voice is unavailable (404/503)", async () => {
        await recordAndStop(vi.fn().mockResolvedValue({ ok: false, kind: "unavailable", message: "التسجيل الصوتي غير متاح حالياً." }));
        expect(screen.getByRole("alert").textContent).toContain("غير متاح");
        expect(screen.getByRole("link", { name: "إدخال يدوي" }).getAttribute("href")).toBe("/manual");
        expect(screen.queryByRole("button", { name: "حاول مرة أخرى" })).toBeNull();
    });
});
