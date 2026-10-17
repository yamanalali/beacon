import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, expect, it, vi } from "vitest";
import type { BookkeepingOptions, TransactionItemsProps, TransactionRow } from "../../types/bookkeeping";

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children }: { href: string; children: ReactNode }) => <a href={href}>{children}</a>,
    useForm: (data: Record<string, unknown>) => {
        let payload = data;
        return { data, errors: {}, processing: false, setData: vi.fn(), transform: (fn: (value: typeof data) => typeof data) => { payload = fn(data); }, post: (url: string) => post(url, payload) };
    },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
import Form from "./Form";
afterEach(() => { cleanup(); post.mockReset(); });

it("submits invoice items during correction without resubmitting cash payments", () => {
    const original = {
        public_id: "T1", type: "sale", transaction_date: "2026-10-01", amount: "100.00", currency: "USD", category: "general_sales",
        payment_status: "partially_paid", counterparty: "Customer", description: "Invoice",
        items: [{ id: 1, description: "Original item", quantity: 2, unit_price: 50, total: 100 }], items_total: 100, items_mismatch: false,
    } as TransactionRow & TransactionItemsProps;
    const options: BookkeepingOptions = { types: [{ value: "sale", label: "بيع" }], payment_statuses: [], currencies: [{ value: "USD", label: "USD" }], default_currency: "USD", categories: { sale: [{ value: "general_sales", label: "مبيعات" }] } };
    render(<Form enterprise={{ public_id: "E1", business_name: "Store" }} options={options} original={original} />);
    expect(screen.queryByLabelText("المبلغ المدفوع الآن")).toBeNull();
    fireEvent.submit(screen.getByLabelText("سبب التصحيح").closest("form")!);
    expect(post).toHaveBeenCalledWith("/transactions/T1/correct", expect.objectContaining({
        payment_status: "partially_paid", items: [expect.objectContaining({ description: "Original item" })],
    }));
    expect(post.mock.calls[0][1]).not.toHaveProperty("paid_amount");
});
