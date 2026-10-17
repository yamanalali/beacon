import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, expect, it, vi } from "vitest";
import type { BookkeepingOptions, TransactionItemsProps, TransactionRow } from "../../types/bookkeeping";

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock("@inertiajs/react", () => ({
    usePage: () => ({ props: { auth: { user: { draft_scope: "account-a" } } } }),
    Link: ({ href, children }: { href: string; children: ReactNode }) => <a href={href}>{children}</a>,
    useForm: (data: Record<string, unknown>) => {
        let payload = data;
        return { data, errors: {}, processing: false, setData: vi.fn(), transform: (fn: (value: typeof data) => typeof data) => { payload = fn(data); }, post: (url: string) => post(url, payload) };
    },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
import Form from "./Form";
afterEach(() => { cleanup(); post.mockReset(); localStorage.clear(); vi.restoreAllMocks(); });

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

it("saves a device draft while offline without posting and restores it for review", () => {
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    const options: BookkeepingOptions = { types: [{ value: "sale", label: "بيع" }], payment_statuses: [], currencies: [{ value: "USD", label: "USD" }], default_currency: "USD", categories: { sale: [] } };
    render(<Form enterprise={{ public_id: "E1", business_name: "Store" }} options={options} original={null} />);
    fireEvent.submit(screen.getByRole('button', { name: 'حفظ العملية' }).closest('form')!);
    expect(post).not.toHaveBeenCalled();
    expect(screen.getAllByRole('status').map((node) => node.textContent).join(' ')).toContain('تم حفظ المسودة');
    expect(screen.getByRole('button', { name: 'استعادة' })).toBeTruthy();
    const stored = JSON.parse(localStorage.getItem('beacon:drafts:account-a:E1')!);
    expect(stored).toHaveLength(1);
    expect(stored[0].id).toMatch(/^[0-9a-f-]{36}$/);
    fireEvent.click(screen.getByRole('button', { name: 'استعادة' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getAllByRole('status').map((node) => node.textContent).join(' ')).toContain('راجع البيانات');
});

it("retries an online submission using the same draft id", () => {
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true);
    const options: BookkeepingOptions = { types: [], payment_statuses: [], currencies: [], default_currency: "SYP", categories: {} };
    render(<Form enterprise={{ public_id: "E1", business_name: "Store" }} options={options} original={null} />);
    const form = screen.getByRole('button', { name: 'حفظ العملية' }).closest('form')!;
    fireEvent.submit(form);
    fireEvent.submit(form);
    expect(post).toHaveBeenCalledTimes(2);
    expect(post.mock.calls[0][1].client_draft_id).toBe(post.mock.calls[1][1].client_draft_id);
});
