import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReviewForm, confidencePercent, hasSuggestions, initialReviewData, isPrefilled, missingRequired, type ReviewFormData, type TransactionDraft } from "./ReviewForm";
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