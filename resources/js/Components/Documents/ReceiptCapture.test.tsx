import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const post = vi.fn();
const routerPost = vi.fn();
const state: { data: { file: File | null; title: string } } = { data: { file: null, title: "" } };
vi.mock("@inertiajs/react", async () => {
    const React = await import("react");
    return {
        usePage: () => ({ props: {} }),
        router: { post: (...a: unknown[]) => routerPost(...a) },
        useForm: () => {
            const [data, setD] = React.useState(state.data);
            return { data, errors: {}, progress: null, setData: (k: "file", v: File | null) => setD((d) => ({ ...d, [k]: v })), clearErrors: () => {}, post: (...a: unknown[]) => post(...a) };
        },
    };
});

import { ReceiptCapture, findNewDocument } from "./ReceiptCapture";
import type { DocumentRow } from "../../types/bookkeeping";

const doc = (id: string) => ({ public_id: id }) as DocumentRow;
beforeEach(() => { post.mockReset(); routerPost.mockReset(); });
afterEach(cleanup);

describe("findNewDocument", () => {
    it("returns the first document not present before the upload", () => {
        expect(findNewDocument(new Set(["a", "b"]), [doc("c"), doc("a"), doc("b")])?.public_id).toBe("c");
        expect(findNewDocument(new Set(["a"]), [doc("a")])).toBeNull();
        expect(findNewDocument(new Set(), undefined)).toBeNull();
    });
});

describe("ReceiptCapture", () => {
    it("offers a rear-camera capture input and a file picker", () => {
        render(<ReceiptCapture enterpriseId="E1" currentIds={[]} />);
        const cam = screen.getByTestId("camera-input");
        expect(cam.getAttribute("accept")).toBe("image/*");
        expect(cam.getAttribute("capture")).toBe("environment");
        expect(screen.getByTestId("file-input").getAttribute("accept")).toContain("application/pdf");
        expect((screen.getByRole("button", { name: /رفع ومراجعة/ }) as HTMLButtonElement).disabled).toBe(true);
    });

    it("uploads, then starts reading the new document", () => {
        post.mockImplementation((_url: string, opts: { onSuccess: (p: unknown) => void }) =>
            opts.onSuccess({ props: { documents: { data: [doc("NEW"), doc("OLD")] } } }));
        render(<ReceiptCapture enterpriseId="E1" currentIds={["OLD"]} />);
        const file = new File(["x"], "receipt.pdf", { type: "application/pdf" });
        fireEvent.change(screen.getByTestId("file-input"), { target: { files: [file] } });
        expect(screen.getByText("receipt.pdf")).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: /رفع ومراجعة/ }));
        expect(post).toHaveBeenCalledWith("/enterprises/E1/documents?extract=1", expect.objectContaining({ forceFormData: true }));
        expect(routerPost).toHaveBeenCalledWith("/documents/NEW/extractions", {}, expect.anything());
    });

    it("stops when the server already opened the review page", () => {
        post.mockImplementation((_u: string, opts: { onSuccess: (p: unknown) => void }) => opts.onSuccess({ component: "Extractions/Show", props: {} }));
        render(<ReceiptCapture enterpriseId="E1" currentIds={[]} />);
        fireEvent.change(screen.getByTestId("file-input"), { target: { files: [new File(["x"], "r.pdf", { type: "application/pdf" })] } });
        fireEvent.click(screen.getByRole("button", { name: /رفع ومراجعة/ }));
        expect(routerPost).not.toHaveBeenCalled();
    });

    it("uses flash.created_document_id when the server returns back", () => {
        post.mockImplementation((_u: string, opts: { onSuccess: (p: unknown) => void }) => opts.onSuccess({ props: { flash: { created_document_id: "FLASH" }, documents: { data: [] } } }));
        render(<ReceiptCapture enterpriseId="E1" currentIds={[]} />);
        fireEvent.change(screen.getByTestId("file-input"), { target: { files: [new File(["x"], "r.pdf", { type: "application/pdf" })] } });
        fireEvent.click(screen.getByRole("button", { name: /رفع ومراجعة/ }));
        expect(routerPost).toHaveBeenCalledWith("/documents/FLASH/extractions", {}, expect.anything());
    });

    it("shows a recoverable error when the new document cannot be found", () => {
        post.mockImplementation((_u: string, opts: { onSuccess: (p: unknown) => void }) => opts.onSuccess({ props: { documents: { data: [doc("OLD")] } } }));
        render(<ReceiptCapture enterpriseId="E1" currentIds={["OLD"]} />);
        fireEvent.change(screen.getByTestId("file-input"), { target: { files: [new File(["x"], "r.pdf", { type: "application/pdf" })] } });
        fireEvent.click(screen.getByRole("button", { name: /رفع ومراجعة/ }));
        expect(screen.getByRole("alert").textContent).toContain("تعذّر");
        expect(routerPost).not.toHaveBeenCalled();
    });
});
