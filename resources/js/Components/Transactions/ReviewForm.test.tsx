import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReviewForm, confidencePercent, hasSuggestions, initialReviewData, type ReviewFormData, type TransactionDraft } from "./ReviewForm";
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
    const [data, set] = useState(initialReviewData(draft, "expense", options, { category: "rent", label: "إيجار", needs_review: false }, "2026-10-16"));
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
    it("prefills from the draft, falling back to today and the default currency", () => {
        const d = initialReviewData(empty, "income", options, null, "2026-10-16");
        expect(d).toMatchObject({ type: "income", transaction_date: "2026-10-16", amount: "", currency: "SYP", category: "", counterparty: "" });
        expect(initialReviewData({ ...empty, suggested_transaction_type: "income" }, "expense", options, null).type).toBe("income");
        expect(initialReviewData({ ...empty, suggested_transaction_type: "bogus" }, "expense", options, null).type).toBe("expense");
    });
});

describe("ReviewForm", () => {
    it("shows manual-entry mode when nothing was read", () => {
        render(<Harness draft={empty} />);
        expect(screen.getByText("إدخال يدوي")).toBeTruthy();
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("");
        expect(screen.queryByText("مقترح")).toBeNull();
    });

    it("prefills OCR values, marks them suggested and warns on low confidence", () => {
        render(<Harness draft={read} />);
        expect(screen.getByText("راجع القيم بعناية")).toBeTruthy();
        expect(screen.getByText("42%")).toBeTruthy();
        expect((screen.getByLabelText("المبلغ") as HTMLInputElement).value).toBe("1250.50");
        expect((screen.getByLabelText("المورد أو الزبون") as HTMLInputElement).value).toBe("مكتبة النور");
        expect(screen.getAllByText("مقترح").length).toBe(3);
        fireEvent.change(screen.getByLabelText("المبلغ"), { target: { value: "1300" } });
        expect(screen.getAllByText("مقترح").length).toBe(2);
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
});
