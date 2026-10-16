import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const get = vi.fn();
vi.mock("@inertiajs/react", () => ({ router: { get: (...a: unknown[]) => get(...a) } }));

import { DataTable, type Column } from "./DataTable";
import { formatNumber } from "./format";

type Row = { id: number; name: string; amount: number };
const rows: Row[] = [
    { id: 1, name: "بيت", amount: 1500000 },
    { id: 2, name: "ألف", amount: 20 },
    { id: 3, name: "جمل", amount: 300 },
];
const columns: Column<Row>[] = [
    { key: "name", header: "الاسم", cell: (r) => r.name, sortable: true, primary: true },
    { key: "amount", header: "المبلغ", cell: (r) => r.amount, sortable: true, format: "number" },
];
const table = () => screen.getAllByRole("table")[0];
const bodyNames = () => within(table()).getAllByRole("row").slice(1).map((r) => (r as HTMLTableRowElement).cells[0].textContent);

beforeEach(() => get.mockReset());
afterEach(cleanup);

describe("formatNumber", () => {
    it("adds thousands separators", () => {
        expect(formatNumber(1000000)).toBe("1,000,000.00");
        expect(formatNumber("2500", "integer")).toBe("2,500");
        expect(formatNumber(null)).toBe("—");
    });
});

describe("DataTable client mode", () => {
    it("keeps the old minimal API and empty state", () => {
        render(<DataTable columns={columns} rows={[]} rowKey={(r) => r.id} emptyTitle="فارغ" />);
        expect(screen.getByText("فارغ")).toBeTruthy();
    });

    it("formats numbers and sorts by column", () => {
        render(<DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />);
        expect(within(table()).getByText("1,500,000.00")).toBeTruthy();
        const btn = within(table()).getByRole("button", { name: /المبلغ/ });
        fireEvent.click(btn);
        expect(bodyNames()).toEqual(["ألف", "جمل", "بيت"]);
        expect(btn.closest("th")?.getAttribute("aria-sort")).toBe("ascending");
        fireEvent.click(btn);
        expect(bodyNames()).toEqual(["بيت", "جمل", "ألف"]);
    });

    it("filters with global search and shows a no-match state", () => {
        render(<DataTable columns={columns} rows={rows} rowKey={(r) => r.id} searchable />);
        fireEvent.change(screen.getByLabelText("بحث"), { target: { value: "جمل" } });
        expect(bodyNames()).toEqual(["جمل"]);
        fireEvent.change(screen.getByLabelText("بحث"), { target: { value: "zzz" } });
        expect(screen.getByText("لا توجد نتائج مطابقة للبحث")).toBeTruthy();
    });

    it("selects rows and runs bulk actions", () => {
        const onClick = vi.fn();
        render(<DataTable columns={columns} rows={rows} rowKey={(r) => r.id} selectable bulkActions={[{ label: "أرشفة", onClick }]} />);
        fireEvent.click(within(table()).getByLabelText("تحديد كل الصفوف"));
        fireEvent.click(screen.getByRole("button", { name: "أرشفة" }));
        expect(onClick).toHaveBeenCalledWith(rows);
    });

    it("hides a column and toggles density", () => {
        const { container } = render(<DataTable columns={columns} rows={rows} rowKey={(r) => r.id} searchable />);
        fireEvent.click(screen.getByRole("button", { name: /الأعمدة/ }));
        fireEvent.click(screen.getByRole("checkbox", { name: "المبلغ" }));
        expect(within(table()).queryByText("المبلغ")).toBeNull();
        fireEvent.click(screen.getByTitle("كثافة العرض"));
        expect(container.querySelector(".app-dt-compact")).toBeTruthy();
    });

    it("renders skeleton rows while loading", () => {
        render(<DataTable columns={columns} rows={[]} rowKey={(r) => r.id} loading />);
        expect(table().getAttribute("aria-busy")).toBe("true");
        expect(table().querySelectorAll(".app-dt-skeleton-row").length).toBe(5);
    });
});

describe("DataTable server mode", () => {
    const paginator = { data: rows, current_page: 1, last_page: 3, per_page: 3, total: 9, from: 1, to: 3, links: [] };

    it("sends sort and page through Inertia without sorting locally", () => {
        render(<DataTable columns={columns} rowKey={(r) => r.id} server={{ paginator, url: "/reports", query: { status: "open" } }} />);
        fireEvent.click(within(table()).getByRole("button", { name: /المبلغ/ }));
        expect(get).toHaveBeenLastCalledWith("/reports", { status: "open", sort: "amount", direction: "asc" },
            expect.objectContaining({ preserveState: true, preserveScroll: true }));
        expect(bodyNames()).toEqual(["بيت", "ألف", "جمل"]);
        fireEvent.click(screen.getByRole("button", { name: /التالي/ }));
        expect(get).toHaveBeenLastCalledWith("/reports", expect.objectContaining({ page: 2, sort: "amount" }), expect.anything());
    });

    it("debounces search to the server and accepts resource meta", () => {
        vi.useFakeTimers();
        render(<DataTable columns={columns} rowKey={(r) => r.id} searchable
            server={{ paginator: { data: rows, meta: { current_page: 2, last_page: 2, total: 6, from: 4, to: 6 }, links: {} }, url: "/x", search: "", debounce: 100 }} />);
        fireEvent.change(screen.getByLabelText("بحث"), { target: { value: "ب" } });
        expect(get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(100);
        expect(get).toHaveBeenCalledWith("/x", { search: "ب" }, expect.anything());
        expect(bodyNames().length).toBe(3);
        vi.useRealTimers();
    });
});
