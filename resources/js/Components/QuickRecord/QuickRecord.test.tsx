import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const props: { auth: unknown } = { auth: {} };
vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props }),
    router: { post: vi.fn() },
    useForm: () => ({ data: { file: null, title: "" }, errors: {}, progress: null, setData: () => {}, clearErrors: () => {}, post: () => {} }),
}));

import { QuickRecordButton, withExtract } from "./QuickRecord";

const qr = { enterprise_public_id: "E1", upload_url: "/enterprises/E1/documents", voice_url: "/ai/enterprises/E1/voice-drafts", manual_url: "/enterprises/E1/transactions/create" };
const allow = (quick: unknown, can = true) => { props.auth = { can: { record_transaction: can }, quick_record: quick }; };
afterEach(cleanup);

describe("QuickRecordButton", () => {
    it("is hidden without permission or quick_record", () => {
        allow(qr, false); const a = render(<QuickRecordButton />); expect(a.container.innerHTML).toBe(""); a.unmount();
        allow(null); const b = render(<QuickRecordButton />); expect(b.container.innerHTML).toBe("");
    });

    it("opens an accessible sheet with three options and closes on Escape", () => {
        allow(qr);
        render(<QuickRecordButton />);
        const trigger = screen.getByRole("button", { name: /سجّل معاملة/ });
        fireEvent.click(trigger);
        const dialog = screen.getByRole("dialog");
        expect(dialog.getAttribute("aria-modal")).toBe("true");
        expect(document.activeElement?.getAttribute("aria-label")).toBe("إغلاق");
        expect(screen.getByRole("button", { name: "صوّر فاتورة" })).toBeTruthy();
        expect(screen.getByRole("button", { name: "سجّل صوت" })).toBeTruthy();
        expect(screen.getByRole("link", { name: "إدخال يدوي" }).getAttribute("href")).toBe(qr.manual_url);
        fireEvent.keyDown(window, { key: "Escape" });
        expect(screen.queryByRole("dialog")).toBeNull();
        expect(document.activeElement).toBe(trigger);
    });

    it("hides the voice option when voice_url is null", () => {
        allow({ ...qr, voice_url: null });
        render(<QuickRecordButton variant="tab" />);
        fireEvent.click(screen.getByRole("button", { name: /سجّل معاملة/ }));
        expect(screen.queryByRole("button", { name: "سجّل صوت" })).toBeNull();
    });

    it("photo option shows the camera capture", () => {
        allow(qr);
        render(<QuickRecordButton />);
        fireEvent.click(screen.getByRole("button", { name: /سجّل معاملة/ }));
        fireEvent.click(screen.getByRole("button", { name: "صوّر فاتورة" }));
        expect(screen.getByTestId("camera-input").getAttribute("capture")).toBe("environment");
    });

    it("adds extract=1 to the upload url once", () => {
        expect(withExtract("/a")).toBe("/a?extract=1");
        expect(withExtract("/a?x=1")).toBe("/a?x=1&extract=1");
        expect(withExtract("/a?extract=1")).toBe("/a?extract=1");
    });
});
