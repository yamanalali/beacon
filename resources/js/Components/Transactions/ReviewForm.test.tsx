import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReviewForm, withInvoicePrefix, confidencePercent, hasSuggestions, initialReviewData, isPrefilled, reviewPayload, missingRequired, type ReviewFormData, type TransactionDraft } from "./ReviewForm";
import type { BookkeepingOptions } from "../../types/bookkeeping";

const options: BookkeepingOptions = {
    types: [{ value: "expense", label: "مصروف" }, { value: "income", label: "دخل" }],
    payment_statuses: [{ value: "paid", label: "مدفوع" }, { value: "partially_paid", label: "مدفوع جزئياً" }],
    currencies: [{ value: "SYP", label: "ل.س" }, { value: "USD", label: "دولار" }],
    default_currency: "SYP",
    categories: { expense: [{ value: "rent", label: "إيجار" }], income: [{ value: "sales", label: "مبيعات" }] },
};
const empty: TransactionDraft = { suggested_date: null, suggested_amount: null, suggested_currency: null, suggested_counterparty: null };
const read: TransactionDraft = { suggested_date: "2026-10-01", suggested_amount: "1250.50", suggested_currency: "USD", suggested_counterparty: "مكتبة النور", confidence: 0.42, needs_review: true };

function Harness({ draft, onSubmit = () => {} }: { draft: TransactionDraft; onSubmit?: (d: ReviewFormData) => void }) {
    const [data, set] = useState(initialReviewData(draft, "expense", options, { category: "rent", label: "إيجار", needs_review: false }));
    return <ReviewForm draft={draft} data={data} setData={(p) => set((d) => ({ ...d, ...p }))} options={options}
        suggestion={{ category: "rent", label: "إيجار", needs_review: false }} onSubmit={() => onSubmit(data)} onReject={() => {}} />;
}
afterEach(cleanup);

describe("helpers", () => {
    it("detects manual drafts and normalises confidence", () => {
        expect(hasSuggestions(empty)).toBe(false);
        expect(hasSuggestions(read)).toBe(true);
        expect(confidencePercent(0.42)).toBe(42);
        expect(confidencePercent("87")).toBe(87);
        expect(confidencePercent(null)).toBeNull();
    });
    it("never defaults unread date/currency to today or the base currency", () => {
        const d = initialReviewData(empty, "income", options, null);
        expect(d).toMatchObject({ type: "income", transaction_date: "", amount: "", currency: "", category: "", counterparty: "" });
        expect(missingRequired(d)).toEqual(["transaction_date", "currency"]);
        expect(missingRequired(initialReviewData(read, "expense", options, null))).toEqual([]);
    });
    it("prefills type from suggested_transaction_type (incl. server typeOptions) and category from the suggestion", () => {
        const sugg = { category: "sales", label: "مبيعات", needs_review: false };
        expect(initialReviewData({ ...empty, suggested_transaction_type: "income" }, "expense", options, sugg)).toMatchObject({ type: "income", category: "sales" });
        expect(initialReviewData({ ...empty, suggested_transaction_type: "sale" }, "expense", options, null, [{ value: "sale", label: "بيع" }]).type).toBe("sale");
        // A suggestion not valid for the resolved type is not prefilled.
        expect(initialReviewData(empty, "expense", options, sugg).category).toBe("");
        expect(initialReviewData({ ...empty, suggested_transaction_type: "income" }, "expense", options, null).type).toBe("income");
        expect(initialReviewData({ ...empty, suggested_transaction_type: "bogus" }, "expense", options, null).type).toBe("expense");
    });
});

describe("ReviewForm", () => {
    it("shows manual-entry mode when nothing was read", () => {
        render(<Harness draft={empty} />);
        expect(screen.getByText("إدخال يدوي")).toBeTruthy();
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("");
        // Only the category suggestion is badged; no read fields.
        expect(screen.getAllByText("مقترح").length).toBe(1);
    });

    it("prefills OCR values, marks them suggested and warns on low confidence", () => {
        render(<Harness draft={read} />);
        expect(screen.getByText("راجع القيم بعناية")).toBeTruthy();
        expect(screen.getByText("42%")).toBeTruthy();
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("1250.50");
        expect((screen.getByLabelText("المورد أو الزبون") as HTMLInputElement).value).toBe("مكتبة النور");
        // amount, currency, date, counterparty, category
        expect(screen.getAllByText("مقترح").length).toBe(5);
        fireEvent.change(screen.getByLabelText(/المبلغ/), { target: { value: "1300" } });
        expect(screen.getAllByText("مقترح").length).toBe(4);
    });

    it("low confidence alone does not warn; only server needs_review does", () => {
        render(<Harness draft={{ ...read, confidence: 0.1, needs_review: false }} />);
        expect(screen.getByText("تمت القراءة")).toBeTruthy();
        expect(screen.queryByText("راجع القيم بعناية")).toBeNull();
    });

    it("switching expense/income clears the category and submits edited data", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={read} onSubmit={onSubmit} />);
        expect((screen.getByLabelText("التصنيف") as HTMLSelectElement).value).toBe("rent");
        fireEvent.click(screen.getByLabelText("دخل"));
        expect((screen.getByLabelText("التصنيف") as HTMLSelectElement).value).toBe("");
        fireEvent.change(screen.getByLabelText("التصنيف"), { target: { value: "sales" } });
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ type: "income", category: "sales", amount: "1250.50", currency: "USD" }));
    });

    it("leaves unread date/currency empty with 'اختر', marks them required and blocks confirm", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={{ ...read, suggested_date: null, suggested_currency: null }} onSubmit={onSubmit} />);
        const currency = screen.getByLabelText(/العملة/) as HTMLSelectElement;
        const date = screen.getByLabelText(/التاريخ/) as HTMLInputElement;
        expect(currency.value).toBe("");
        expect(currency.options[0].textContent).toBe("اختر");
        expect(currency.required).toBe(true);
        expect(date.value).toBe("");
        expect(date.required).toBe(true);
        const confirm = screen.getByRole("button", { name: /تأكيد وحفظ/ }) as HTMLButtonElement;
        expect(confirm.disabled).toBe(true);
        expect(screen.getByText(/أكمل الحقول المطلوبة/).textContent).toContain("العملة");
        fireEvent.submit(confirm.closest("form")!);
        expect(onSubmit).not.toHaveBeenCalled();
        fireEvent.change(currency, { target: { value: "SYP" } });
        fireEvent.change(date, { target: { value: "2026-10-15" } });
        expect(confirm.disabled).toBe(false);
        expect(screen.queryByText(/أكمل الحقول المطلوبة/)).toBeNull();
    });
});

describe("«مقترح» badge", () => {
    it("isPrefilled needs a non-empty suggestion equal to the current value", () => {
        expect(isPrefilled("100", "100")).toBe(true);
        expect(isPrefilled("100", "90")).toBe(false);
        expect(isPrefilled(null, "")).toBe(false);
        expect(isPrefilled(undefined, "")).toBe(false);
        expect(isPrefilled("", "")).toBe(false);
        expect(isPrefilled("  ", "  ")).toBe(false);
    });

    it("shows a badge for every field with a non-empty prefilled value", () => {
        const full: TransactionDraft = { ...read, suggested_transaction_type: "expense" };
        render(<Harness draft={full} />);
        // type, amount, currency, date, counterparty, category
        expect(screen.getAllByText("مقترح").length).toBe(6);
    });

    it("shows no badge for empty or null suggestions", () => {
        const blank: TransactionDraft = { suggested_date: "", suggested_amount: "", suggested_currency: null, suggested_counterparty: "   ", suggested_transaction_type: null };
        const set = vi.fn();
        render(<ReviewForm draft={blank} data={initialReviewData(blank, "expense", options, null)} setData={set} options={options} suggestion={{ category: "", label: "", needs_review: false }} onSubmit={() => {}} />);
        expect(screen.queryByText("مقترح")).toBeNull();
    });
});

describe("suggested_note", () => {
    const noteBox = () => screen.getByLabelText(/ملاحظة/) as HTMLInputElement;
    const renderWith = (draft: TransactionDraft) =>
        render(<ReviewForm draft={draft} data={initialReviewData(draft, "expense", options, null)} setData={() => {}} options={options} suggestion={null} onSubmit={() => {}} />);
    const base: TransactionDraft = { suggested_date: null, suggested_amount: null, suggested_currency: null, suggested_counterparty: null };

    it("prefills the note and shows the badge when filled", () => {
        expect(initialReviewData({ ...base, suggested_note: "إيجار شهر تشرين" }, "expense", options, null).description).toBe("إيجار شهر تشرين");
        renderWith({ ...base, suggested_note: "إيجار شهر تشرين" });
        expect(noteBox().value).toBe("إيجار شهر تشرين");
        expect(screen.getAllByText("مقترح").length).toBe(1);
    });

    it.each([null, "", "   "])("leaves the note empty with no badge for %j", (note) => {
        expect(initialReviewData({ ...base, suggested_note: note }, "expense", options, null).description).toBe("");
        renderWith({ ...base, suggested_note: note });
        expect(noteBox().value).toBe("");
        expect(screen.queryByText("مقترح")).toBeNull();
    });

    it("is safe when the server doesn't send suggested_note", () => {
        expect(initialReviewData(base, "expense", options, null).description).toBe("");
        renderWith(base);
        expect(noteBox().value).toBe("");
        expect(screen.queryByText("مقترح")).toBeNull();
    });
});

describe("suggested_items in the review form", () => {
    const base: TransactionDraft = { suggested_date: "2026-10-01", suggested_amount: "3000", suggested_currency: "SYP", suggested_counterparty: null };
    function ItemsHarness({ draft, onSubmit }: { draft: TransactionDraft; onSubmit: (p: ReturnType<typeof reviewPayload>) => void }) {
        const [data, set] = useState(initialReviewData(draft, "expense", options, null));
        return <ReviewForm draft={draft} data={data} setData={(p) => set((d) => ({ ...d, ...p }))} options={options} suggestion={null} onSubmit={() => onSubmit(reviewPayload(data))} />;
    }

    it("prefills rows from suggested_items with «مقترح» badges; nulls stay empty", () => {
        render(<ItemsHarness draft={{ ...base, suggested_items: [{ description: "قماش", quantity: 2, unit_price: 1500, total: null }, { description: "خيوط", quantity: null, unit_price: null, total: 0 }] }} onSubmit={() => {}} />);
        expect((screen.getByLabelText("الوصف — البند 1") as HTMLInputElement).value).toBe("قماش");
        expect((screen.getByLabelText("الإجمالي — البند 1") as HTMLInputElement).value).toBe("3000");
        expect((screen.getByLabelText("الكمية — البند 2") as HTMLInputElement).value).toBe("");
        expect((screen.getByLabelText("سعر الوحدة — البند 2") as HTMLInputElement).value).toBe("");
        const editor = screen.getByTestId("items-editor");
        // row 1: description, quantity, unit_price; row 2: description, total (0 is a real value)
        expect(editor.querySelectorAll(".app-status--warning").length).toBe(5);
        fireEvent.change(screen.getByLabelText("الوصف — البند 1"), { target: { value: "قماش قطني" } });
        expect(editor.querySelectorAll(".app-status--warning").length).toBe(4);
    });

    it("no suggested_items (missing or null) → no rows, items: [] in payload", () => {
        const onSubmit = vi.fn();
        render(<ItemsHarness draft={{ ...base, suggested_items: null }} onSubmit={onSubmit} />);
        expect(screen.getByText("لا توجد بنود")).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ items: [] }));
        expect(initialReviewData(base, "expense", options, null).items).toEqual([]);
    });

    it("sends items[] and still allows saving when the items total mismatches", () => {
        const onSubmit = vi.fn();
        render(<ItemsHarness draft={{ ...base, suggested_amount: "3500", suggested_items: [{ description: "قماش", quantity: 2, unit_price: 1500, total: null }] }} onSubmit={onSubmit} />);
        fireEvent.click(screen.getByRole("button", { name: /إضافة بند/ }));
        expect(screen.getByTestId("review-items-mismatch")).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(onSubmit.mock.calls[0][0].items).toEqual([{ description: "قماش", quantity: "2", unit_price: "1500" }]);
    });
});


describe("suggested_currency_source hint", () => {
    const base: TransactionDraft = { suggested_date: "2026-10-01", suggested_amount: "3000", suggested_currency: "USD", suggested_counterparty: null };
    const hint = "العملة الافتراضية، تأكد منها";

    it("shows the hint next to the مقترح badge when the currency is the enterprise default", () => {
        render(<Harness draft={{ ...base, suggested_currency_source: "default" }} onSubmit={vi.fn()} />);
        expect(screen.getByTestId("currency-default-hint").textContent).toBe(hint);
    });

    it("does not show the hint when the currency was spoken", () => {
        render(<Harness draft={{ ...base, suggested_currency_source: "spoken" }} onSubmit={vi.fn()} />);
        expect(screen.queryByText(hint)).toBeNull();
    });

    it("does not show the hint when the source is missing or null", () => {
        render(<Harness draft={base} onSubmit={vi.fn()} />);
        expect(screen.queryByText(hint)).toBeNull();
        cleanup();
        render(<Harness draft={{ ...base, suggested_currency_source: null }} onSubmit={vi.fn()} />);
        expect(screen.queryByText(hint)).toBeNull();
    });
});
describe("voice amount warning, items mismatch and rent prefill", () => {
    const rent: TransactionDraft = {
        suggested_date: "2026-10-16", suggested_amount: "3000", suggested_currency: "SYP", suggested_counterparty: null,
        suggested_note: "لمدة 3 اشهر",
        suggested_items: [{ description: "إيجار محل (شهري)", quantity: 3, unit_price: 1000, total: 3000 }],
        items_mismatch: false, suggested_amount_warning: null,
    };
    const warning = "تأكد: هل المبلغ 3000 أم ثلاثة آلاف لكل شهر؟";

    it("prefills the rent example from the voice shape", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={rent} onSubmit={onSubmit} />);
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("3000");
        expect((screen.getByLabelText(/ملاحظة/) as HTMLInputElement).value).toBe("لمدة 3 اشهر");
        expect(screen.getAllByTestId("item-row")).toHaveLength(1);
        expect(screen.getByDisplayValue("إيجار محل (شهري)")).toBeTruthy();
        expect(screen.getByTestId("items-total").textContent).toContain("3,000");
        expect(screen.queryByTestId("review-items-mismatch")).toBeNull();
        expect(screen.queryByTestId("items-mismatch")).toBeNull();
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        const items = reviewPayload(onSubmit.mock.calls[0][0]).items;
        expect(items).toHaveLength(1);
        expect(items[0]).toMatchObject({ description: "إيجار محل (شهري)", quantity: "3", unit_price: "1000" });
        expect(Number(items[0].total ?? 3000)).toBe(3000);
    });

    it("shows suggested_amount_warning only while the amount is the suggested one", () => {
        render(<Harness draft={{ ...rent, suggested_amount_warning: warning }} />);
        expect(screen.getByTestId("amount-warning").textContent).toContain(warning);
        fireEvent.change(screen.getByLabelText("المبلغ"), { target: { value: "9000" } });
        expect(screen.queryByTestId("amount-warning")).toBeNull();
        fireEvent.change(screen.getByLabelText("المبلغ"), { target: { value: "3000" } });
        expect(screen.getByTestId("amount-warning")).toBeTruthy();
    });

    it("hides the warning when null or blank", () => {
        render(<Harness draft={{ ...rent, suggested_amount_warning: "  " }} />);
        expect(screen.queryByTestId("amount-warning")).toBeNull();
    });

    it("shows a non-blocking mismatch above the items when the total differs, within the SYP margin otherwise", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={rent} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByLabelText("المبلغ"), { target: { value: "3000.4" } });
        expect(screen.queryByTestId("review-items-mismatch")).toBeNull();
        fireEvent.change(screen.getByLabelText("المبلغ"), { target: { value: "2500" } });
        const note = screen.getByTestId("review-items-mismatch");
        expect(note.compareDocumentPosition(screen.getByTestId("items-editor")) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(screen.queryByTestId("items-mismatch")).toBeNull();
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(onSubmit).toHaveBeenCalledTimes(1);
    });

    it("uses the tight margin for other currencies", () => {
        render(<Harness draft={{ ...rent, suggested_currency: "USD", suggested_amount: "3000.01" }} />);
        expect(screen.getByTestId("review-items-mismatch")).toBeTruthy();
    });

    it("shows the server items_mismatch flag until the amount or items are edited", () => {
        render(<Harness draft={{ ...rent, items_mismatch: true }} />);
        expect(screen.getByTestId("review-items-mismatch")).toBeTruthy();
        fireEvent.change(screen.getByDisplayValue("إيجار محل (شهري)"), { target: { value: "إيجار محل" } });
        expect(screen.queryByTestId("review-items-mismatch")).toBeNull();
    });
});
describe("invoice number", () => {
    const ocr: TransactionDraft = {
        suggested_date: "2026-10-16", suggested_amount: "41500", suggested_currency: "SYP", suggested_counterparty: null,
        suggested_invoice_number: "INV-77", suggested_note: "فاتورة رقم INV-77",
    };

    it("keeps one «فاتورة رقم X» prefix", () => {
        expect(withInvoicePrefix("", "INV-1")).toBe("فاتورة رقم INV-1");
        expect(withInvoicePrefix("فاتورة رقم INV-1", "INV-1")).toBe("فاتورة رقم INV-1");
        expect(withInvoicePrefix("فاتورة رقم INV-1 - شراء أقمشة", "INV-2")).toBe("فاتورة رقم INV-2 - شراء أقمشة");
        expect(withInvoicePrefix("شراء أقمشة", "77")).toBe("فاتورة رقم 77 - شراء أقمشة");
        expect(withInvoicePrefix("فاتورة رقم 5 شراء", "")).toBe("شراء");
        expect(withInvoicePrefix("شراء", " ")).toBe("شراء");
    });

    it("shows the suggested number with the badge and the server note is not duplicated", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={ocr} onSubmit={onSubmit} />);
        const field = screen.getByLabelText("رقم الفاتورة") as HTMLInputElement;
        expect(field.value).toBe("INV-77");
        let el: Element | null = field; while (el && !el.nextElementSibling) el = el.parentElement;
        expect(el?.nextElementSibling?.textContent ?? "").toContain("مقترح");
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        const payload = reviewPayload(onSubmit.mock.calls[0][0]);
        expect(payload.description).toBe("فاتورة رقم INV-77");
        expect("invoice_number" in payload).toBe(false);
    });

    it("syncs the note prefix when the number is edited or cleared", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={{ ...ocr, suggested_note: "فاتورة رقم INV-77 - أقمشة" }} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByLabelText("رقم الفاتورة"), { target: { value: "INV-78" } });
        expect((screen.getByLabelText(/ملاحظة/) as HTMLInputElement).value).toBe("فاتورة رقم INV-78 - أقمشة");
        fireEvent.change(screen.getByLabelText("رقم الفاتورة"), { target: { value: "" } });
        expect((screen.getByLabelText(/ملاحظة/) as HTMLInputElement).value).toBe("أقمشة");
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(reviewPayload(onSubmit.mock.calls[0][0]).description).toBe("أقمشة");
    });

    it("prepends on submit when the user typed a number and a note without the prefix", () => {
        const onSubmit = vi.fn();
        render(<Harness draft={{ ...ocr, suggested_invoice_number: null, suggested_note: null }} onSubmit={onSubmit} />);
        expect((screen.getByLabelText("رقم الفاتورة") as HTMLInputElement).value).toBe("");
        fireEvent.change(screen.getByLabelText(/ملاحظة/), { target: { value: "توصيل" } });
        fireEvent.change(screen.getByLabelText("رقم الفاتورة"), { target: { value: "12" } });
        fireEvent.change(screen.getByLabelText(/ملاحظة/), { target: { value: "توصيل" } });
        fireEvent.click(screen.getByRole("button", { name: /تأكيد وحفظ/ }));
        expect(reviewPayload(onSubmit.mock.calls[0][0]).description).toBe("فاتورة رقم 12 - توصيل");
    });
});