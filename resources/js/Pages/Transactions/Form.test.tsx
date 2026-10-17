import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, expect, it, vi } from "vitest";
import type { BookkeepingOptions, TransactionItemsProps, TransactionRow } from "../../types/bookkeeping";

const { post, testErrors } = vi.hoisted(() => ({ post: vi.fn(), testErrors: {} as Record<string, string> }));
vi.mock("@inertiajs/react", async () => {
    const { useState, useRef } = await import("react");
    return {
        usePage: () => ({ props: { auth: { user: { draft_scope: "account-a" } } } }),
        Link: ({ href, children }: { href: string; children: ReactNode }) => <a href={href}>{children}</a>,
        useForm: (initial: Record<string, unknown>) => {
            const [data, setData] = useState(initial);
            const transform = useRef((value: typeof data) => value);
            return { data, errors: testErrors, processing: false,
                setData: (key: string | Record<string, unknown> | ((data: typeof initial) => typeof initial), value?: unknown) => {
                    if (typeof key === "function") setData(key);
                    else if (typeof key === "string") setData(data => ({ ...data, [key]: value }));
                    else setData(key);
                },
                transform: (fn: (value: typeof data) => typeof data) => { transform.current = fn; },
                post: (url: string) => post(url, transform.current(data)),
            };
        },
    };
});
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
import Form from "./Form";
afterEach(() => { cleanup(); post.mockReset(); localStorage.clear(); vi.restoreAllMocks(); Object.keys(testErrors).forEach(key => delete testErrors[key]); });

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

const simpleOptions: BookkeepingOptions = {
    types: [{ value: 'sale', label: 'بيع' }, { value: 'purchase', label: 'شراء' }, { value: 'expense', label: 'مصروف' }],
    payment_statuses: [{ value: 'paid', label: 'مدفوع' }, { value: 'unpaid', label: 'غير مدفوع' }, { value: 'partially_paid', label: 'دفع جزئي' }],
    currencies: [{ value: 'USD', label: 'دولار أمريكي' }], default_currency: 'USD',
    categories: { sale: [{ value: 'general_sales', label: 'مبيعات عامة' }], purchase: [{ value: 'goods', label: 'بضائع' }], expense: [{ value: 'rent', label: 'إيجار' }] },
};
const business = { public_id: 'E1', business_name: 'Store' };

it('starts with optional details collapsed and switches categories when the type changes', () => {
    render(<Form enterprise={business} options={simpleOptions} original={null} counterparties={['Customer A']} />);
    expect(document.querySelector('details')!.open).toBe(false);
    expect(String((screen.getByRole('radio', { name: 'بيع' }) as HTMLInputElement).checked)).toBe('true');
    fireEvent.change(screen.getByLabelText(/التصنيف/), { target: { value: 'general_sales' } });
    fireEvent.click(screen.getByRole('radio', { name: 'شراء' }));
    expect((screen.getByLabelText(/التصنيف/) as HTMLSelectElement).value).toBe('');
    expect(screen.getByRole('option', { name: 'بضائع' })).toBeTruthy();
    expect(screen.queryByRole('option', { name: 'مبيعات عامة' })).toBeNull();
    expect(document.querySelector('#counterparty-suggestions option')?.getAttribute('value')).toBe('Customer A');
    expect(screen.getByLabelText(/اسم المورد/)).toBeTruthy();
});

it('shows exact totals for Arabic amounts and only asks for a payment on partial payment', () => {
    render(<Form enterprise={business} options={simpleOptions} original={null} requires_approval />);
    const summary = screen.getByRole('region', { name: 'ملخص العملية' });
    fireEvent.change(screen.getByLabelText(/^المبلغ/), { target: { value: '٥٠٠٫٥٠' } });
    expect(summary.textContent).toContain('500.50 USD');
    expect(screen.queryByLabelText(/المبلغ المدفوع الآن/)).toBeNull();
    fireEvent.change(screen.getByLabelText('هل تم الدفع؟'), { target: { value: 'partially_paid' } });
    fireEvent.change(screen.getByLabelText(/المبلغ المدفوع الآن/), { target: { value: '٢٠٠٫٢٥' } });
    expect(summary.textContent).toContain('200.25 USD');
    expect(summary.textContent).toContain('300.25 USD');
    expect(screen.getByRole('button', { name: 'إرسال للموافقة' })).toBeTruthy();
    fireEvent.submit(screen.getByRole('button', { name: 'إرسال للموافقة' }).closest('form')!);
    expect(post.mock.calls[0][1]).toMatchObject({ amount: '500.50', paid_amount: '200.25' });
    fireEvent.change(screen.getByLabelText('هل تم الدفع؟'), { target: { value: 'unpaid' } });
    expect(screen.queryByLabelText(/المبلغ المدفوع الآن/)).toBeNull();
    expect(summary.textContent).toContain('0.00 USD');
    expect(summary.textContent).toContain('500.50 USD');
});

it('opens optional details and focuses an invalid field after validation fails', async () => {
    testErrors.description = 'الملاحظات طويلة جداً.';
    render(<Form enterprise={business} options={simpleOptions} original={null} />);
    await waitFor(() => expect(document.querySelector('details')!.open).toBe(true));
    expect(screen.getByText('الملاحظات طويلة جداً.')).toBeTruthy();
    await waitFor(() => expect(document.activeElement).toBe(screen.getByLabelText('ملاحظات (اختياري)')));
});

it('copies invoice details for review but resets the date and payment instead of copying old cash', () => {
    const template = { public_id: 'T1', type: 'sale', transaction_date: '2020-01-01', amount: '100.00', currency: 'USD', category: 'general_sales', payment_status: 'paid', counterparty: 'Old customer', description: 'Old note', items: [] } as unknown as TransactionRow;
    render(<Form enterprise={business} options={simpleOptions} original={null} template={template} />);
    expect((screen.getByLabelText('هل تم الدفع؟') as HTMLSelectElement).value).toBe('unpaid');
    expect((screen.getByLabelText(/التاريخ/) as HTMLInputElement).value).not.toBe('2020-01-01');
    expect(document.querySelector('details')!.open).toBe(true);
    const summary = within(screen.getByRole('region', { name: 'ملخص العملية' }));
    expect(summary.getByText('0.00 USD')).toBeTruthy();
    fireEvent.submit(screen.getByRole('button', { name: 'حفظ العملية' }).closest('form')!);
    expect(post.mock.calls[0][1]).toMatchObject({ amount: '100.00', payment_status: 'unpaid', paid_amount: '' });
});

it('does not silently select a different currency when copying a disabled historical currency', () => {
    const template = { type: 'sale', amount: '100', currency: 'SYP', category: 'general_sales', items: [] } as unknown as TransactionRow;
    render(<Form enterprise={business} options={simpleOptions} original={null} template={template} />);
    expect((screen.getByLabelText(/العملة/) as HTMLSelectElement).value).toBe('');
    expect(screen.getByRole('note').textContent).toContain('لا يتم تحويل القيم تلقائياً');
});
