import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { ItemsEditor, ItemsTable, itemsMismatch, itemsTotal, rowsFromItems, toItemsPayload } from "./ItemsEditor";
import type { TransactionItem } from "../../types/bookkeeping";

// 4-row sales invoice fixture: total 41,500 matches the amount, so no warning.
export const invoiceItems = [
    { id: 1, description: "لابتوب", quantity: 2, unit_price: 12000, total: 24000 },
    { id: 2, description: "سكانر", quantity: 1, unit_price: 4000, total: 4000 },
    { id: 3, description: "طابعة ليزر", quantity: 1, unit_price: 6000, total: 6000 },
    { id: 4, description: "شاشة سامسونج", quantity: 3, unit_price: 2500, total: 7500 },
] as unknown as TransactionItem[];

afterEach(cleanup);

describe("sales invoice fixture (4 rows, 41,500)", () => {
    const rows = rowsFromItems(invoiceItems);

    it("computes line totals and matches the amount", () => {
        expect(rows).toHaveLength(4);
        expect(rows.every((r) => !r.total_manual)).toBe(true);
        expect(itemsTotal(rows)).toBe(41500);
        expect(itemsMismatch(rows, "41500")).toBe(false);
        expect(toItemsPayload(rows).map((i) => [Number(i.quantity), Number(i.unit_price)])).toEqual([[2, 12000], [1, 4000], [1, 6000], [3, 2500]]);
        expect(toItemsPayload(rows).some((i) => "total" in i)).toBe(false);
    });

    it("editor renders 4 rows, total 41,500 and no mismatch", () => {
        render(<ItemsEditor rows={rows} onChange={() => {}} currency="SYP" amount="41500" />);
        expect(screen.getAllByTestId("item-row")).toHaveLength(4);
        expect(screen.getByTestId("items-total").textContent).toContain("41,500");
        expect(screen.queryByTestId("items-mismatch")).toBeNull();
    });

    it("show table renders 4 rows, total 41,500 and no mismatch", () => {
        render(<ItemsTable items={invoiceItems} itemsTotal={41500} mismatch={false} amount="41500" currency="SYP" />);
        const table = screen.getByTestId("items-table");
        for (const d of ["لابتوب", "سكانر", "طابعة ليزر", "شاشة سامسونج"]) expect(table.textContent).toContain(d);
        expect(table.textContent).toContain("24,000");
        expect(table.textContent).toContain("7,500");
        expect(table.textContent).toContain("41,500");
        expect(screen.queryByTestId("items-mismatch")).toBeNull();
    });
});