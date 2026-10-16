import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { useState } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ItemsEditor, ItemsTable, computeLineTotal, emptyRow, isEmptyRow, itemsMismatch, itemsTotal, rowsFromItems, rowsFromSuggested, toItemsPayload, type ItemRow } from "./ItemsEditor";

afterEach(cleanup);

const row = (p: Partial<ItemRow>): ItemRow => ({ ...emptyRow(), ...p });

describe("items model", () => {
    it("computes q × p with half-up rounding and honours a typed total", () => {
        expect(computeLineTotal("3", "2.335")).toBe(7.01);
        expect(computeLineTotal("2", "")).toBeNull();
        expect(itemsTotal([row({ description: "a", quantity: "2", unit_price: "1500" }), row({ description: "b", total: "99", total_manual: true })])).toBe(3099);
        expect(itemsTotal([emptyRow()])).toBeNull();
    });
    it("payload drops fully empty rows and sends total only when typed", () => {
        const rows = [row({ description: " قماش ", quantity: "2", unit_price: "1500" }), emptyRow(), row({ description: "توصيل", quantity: "1", unit_price: "500", total: "450", total_manual: true })];
        expect(toItemsPayload(rows)).toEqual([
            { description: "قماش", quantity: "2", unit_price: "1500" },
            { description: "توصيل", quantity: "1", unit_price: "500", total: "450" },
        ]);
        expect(isEmptyRow(row({ quantity: "1" }))).toBe(true);
        expect(isEmptyRow(row({ quantity: "3" }))).toBe(false);
    });
    it("mismatch is a pure comparison against the amount", () => {
        const rows = [row({ description: "a", quantity: "2", unit_price: "100" })];
        expect(itemsMismatch(rows, "200")).toBe(false);
        expect(itemsMismatch(rows, "250.00")).toBe(true);
        expect(itemsMismatch(rows, "")).toBe(false);
        expect(itemsMismatch([], "250")).toBe(false);
    });
    it("suggested items: nulls stay empty, all-null items skipped, non-array safe", () => {
        const r = rowsFromSuggested([
            { description: "خبز", quantity: 2, unit_price: null, total: null },
            { description: null, quantity: null, unit_price: null, total: null },
            { description: null, quantity: null, unit_price: 5, total: 10 },
        ]);
        expect(r).toHaveLength(2);
        expect(r[0]).toMatchObject({ description: "خبز", quantity: "2", unit_price: "", total: "", total_manual: false });
        expect(r[1]).toMatchObject({ description: "", quantity: "", unit_price: "5", total: "10", total_manual: true });
        expect(rowsFromSuggested(null)).toEqual([]);
        expect(rowsFromSuggested(undefined)).toEqual([]);
    });
    it("saved items: computed totals are not marked manual", () => {
        const r = rowsFromItems([{ id: 1, description: "a", quantity: 2, unit_price: 10, total: 20 }, { id: 2, description: "b", quantity: 1, unit_price: 10, total: 8 }]);
        expect(r.map((x) => x.total_manual)).toEqual([false, true]);
        expect(rowsFromItems(undefined)).toEqual([]);
    });
});

function Harness({ initial = [] as ItemRow[], amount = "3000", onRows }: { initial?: ItemRow[]; amount?: string; onRows?: (r: ItemRow[]) => void }) {
    const [rows, setRows] = useState(initial);
    return (
        <form onSubmit={(e) => e.preventDefault()}>
            <ItemsEditor rows={rows} onChange={(r) => { setRows(r); onRows?.(r); }} currency="SYP" amount={amount} />
            <button type="submit">حفظ</button>
        </form>
    );
}

describe("<ItemsEditor>", () => {
    it("empty state offers «إضافة بند»; add, edit, delete rows", () => {
        const onRows = vi.fn();
        render(<Harness onRows={onRows} />);
        expect(screen.getByText("لا توجد بنود")).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: /إضافة بند/ }));
        expect(screen.getAllByTestId("item-row")).toHaveLength(1);
        fireEvent.change(screen.getByLabelText("الوصف — البند 1"), { target: { value: "قماش" } });
        fireEvent.change(screen.getByLabelText("الكمية — البند 1"), { target: { value: "2" } });
        fireEvent.change(screen.getByLabelText("سعر الوحدة — البند 1"), { target: { value: "1500" } });
        expect((screen.getByLabelText("الإجمالي — البند 1") as HTMLInputElement).value).toBe("3000");
        expect(screen.getByTestId("items-total").textContent).toContain("3,000 ل.س");
        expect(screen.queryByTestId("items-mismatch")).toBeNull();
        fireEvent.click(screen.getByRole("button", { name: /إضافة بند/ }));
        expect(screen.getAllByTestId("item-row")).toHaveLength(2);
        fireEvent.click(screen.getByRole("button", { name: "حذف البند 2" }));
        expect(screen.getAllByTestId("item-row")).toHaveLength(1);
        fireEvent.click(screen.getByRole("button", { name: "حذف البند 1" }));
        expect(screen.getByText("لا توجد بنود")).toBeTruthy();
    });

    it("typed total overrides q × p and shows the mismatch warning without blocking", () => {
        render(<Harness initial={[row({ description: "قماش", quantity: "2", unit_price: "1500" })]} />);
        fireEvent.change(screen.getByLabelText("الإجمالي — البند 1"), { target: { value: "2800" } });
        expect(screen.getByTestId("items-total").textContent).toContain("2,800 ل.س");
        expect(screen.getByTestId("items-mismatch").textContent).toContain("يمكنك الحفظ");
        expect((screen.getByRole("button", { name: "حفظ" }) as HTMLButtonElement).disabled).toBe(false);
        // clearing the typed total returns to q × p
        fireEvent.change(screen.getByLabelText("الإجمالي — البند 1"), { target: { value: "" } });
        expect((screen.getByLabelText("الإجمالي — البند 1") as HTMLInputElement).value).toBe("3000");
    });

    it("shows server errors per field", () => {
        render(<ItemsEditor rows={[row({ description: "" , unit_price: "5" })]} onChange={() => {}} currency="USD" amount="5" errors={{ "items.0.description": "اكتب وصف البند." }} />);
        expect(screen.getByText("اكتب وصف البند.")).toBeTruthy();
        expect(screen.getByLabelText("الوصف — البند 1").getAttribute("aria-invalid")).toBe("true");
    });
});

describe("<ItemsTable>", () => {
    const items = [{ id: 1, description: "قماش", quantity: 2, unit_price: 1500, total: 3000 }];
    it("renders items, total and mismatch", () => {
        render(<ItemsTable items={items} itemsTotal={3000} mismatch amount="3500.00" currency="SYP" />);
        const table = within(screen.getByRole("table"));
        expect(table.getByText("قماش")).toBeTruthy();
        expect(screen.getAllByText("3,000 ل.س").length).toBeGreaterThan(0);
        expect(screen.getByTestId("items-mismatch").textContent).toContain("3,500 ل.س");
    });
    it("empty state without items", () => {
        render(<ItemsTable items={[]} itemsTotal={null} mismatch={false} amount="1" currency="SYP" />);
        expect(screen.getByText("لا توجد بنود لهذه العملية")).toBeTruthy();
    });
});
