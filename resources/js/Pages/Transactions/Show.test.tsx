import { cleanup, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children }: { href: string; children: ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({ props: { auth: {}, flash: {} } }),
    useForm: (d: Record<string, unknown>) => ({ data: d, errors: {}, setData: () => {}, post: () => {}, processing: false }),
    router: { post: vi.fn(), reload: vi.fn() },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));
vi.mock("../../Components/Documents/DocumentUploader", () => ({ DocumentUploader: () => null }));
vi.mock("../../Components/Documents/DocumentList", () => ({ DocumentList: () => null }));

import Show from "./Show";

afterEach(cleanup);

const tx = {
    public_id: "T1", type: "purchase", type_label: "شراء", is_inflow: false, transaction_date: "2026-10-16", amount: "3500.00", currency: "SYP",
    category: "materials", category_label: "مواد", payment_status: "paid", payment_status_label: "مدفوع", outstanding: "0.00", counterparty: null,
    description: null, status: "posted", status_label: "مرحّلة", is_superseded: false, creator_name: null, correction_reason: null, created_at: null,
    corrects_public_id: null, corrected_by_public_id: null,
} as const;

describe("Transactions/Show items", () => {
    it("renders the read-only items with total and mismatch warning", () => {
        const props = { enterprise: { public_id: "E1", business_name: "x" }, transaction: { ...tx, items: [{ id: 1, description: "قماش", quantity: 2, unit_price: 1500, total: 3000 }], items_total: 3000, items_mismatch: true }, settlements: [], documents: [], can_correct: false, can_settle: false, can_upload: false };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        render(<Show {...(props as any)} />);
        expect(screen.getByTestId("items-table")).toBeTruthy();
        expect(screen.getByTestId("items-mismatch")).toBeTruthy();
    });
    it("is safe when the server sends no items props", () => {
        const props = { enterprise: { public_id: "E1", business_name: "x" }, transaction: tx, settlements: [], documents: [], can_correct: false, can_settle: false, can_upload: false };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        render(<Show {...(props as any)} />);
        expect(screen.getByText("لا توجد بنود لهذه العملية")).toBeTruthy();
    });
});
