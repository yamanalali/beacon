import { act, cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { post: vi.fn() } }));

import { RuleSetBuilder } from "./RuleSetBuilder";
import { draft } from "./fixtures.test-data";
import type { RuleSetDraft } from "./types";

afterEach(() => { cleanup(); vi.useRealTimers(); });

const saved = () => {
    const onSave = vi.fn<(rs: RuleSetDraft) => void>();
    return { onSave, last: () => onSave.mock.calls.at(-1)![0] };
};

describe("RuleSetBuilder editor", () => {
    it("renders pillars, criteria and the implicit pillar with no warnings for a valid set", () => {
        render(<RuleSetBuilder ruleSet={draft} previewFetcher={null} />);
        const cards = screen.getAllByTestId("pillar-card");
        expect(cards).toHaveLength(3);
        expect(within(cards[0]).getAllByTestId("criterion-row")).toHaveLength(2);
        expect(cards[2].textContent).toContain("معايير غير مصنفة");
        expect(screen.queryByTestId("validation")).toBeNull();
        expect(within(cards[0]).getByText("إلزامي")).toBeTruthy();
    });

    it("adds a pillar and shows pre-publish warnings until it has a weight", () => {
        const s = saved();
        render(<RuleSetBuilder ruleSet={draft} onSave={s.onSave} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("button", { name: /إضافة محور/ }));
        expect(screen.getByTestId("validation").textContent).toMatch(/وزن المحور/);
        const card = screen.getAllByTestId("pillar-card")[2];
        fireEvent.change(within(card).getByLabelText("الرمز"), { target: { value: "C" } });
        fireEvent.change(within(card).getByLabelText("اسم المحور"), { target: { value: "محور ج" } });
        fireEvent.change(within(card).getByLabelText("الوزن"), { target: { value: "1" } });
        expect(screen.queryByTestId("validation")).toBeNull();
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        expect(s.last().pillars.find((p) => p.code === "C")).toMatchObject({ label_ar: "محور ج", weight: "1", sort_order: 2 });
    });

    it("reorders pillars and criteria with the move buttons and drag and drop", () => {
        const s = saved();
        render(<RuleSetBuilder ruleSet={draft} onSave={s.onSave} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("button", { name: "نقل محور ب للأعلى" }));
        fireEvent.click(screen.getByRole("button", { name: "نقل نسبة التحصيل للأعلى" }));
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        let rs = s.last();
        expect([...rs.pillars].sort((a, b) => a.sort_order - b.sort_order).map((p) => p.code)).toEqual(["B", "A"]);
        expect(rs.criteria.filter((c) => c.readiness_pillar_id === 20).sort((a, b) => a.sort_order - b.sort_order).map((c) => c.code)).toEqual(["b2", "b1"]);

        const rows = within(screen.getAllByTestId("pillar-card")[1]).getAllByTestId("criterion-row");
        const dt = { effectAllowed: "", setData: () => {} };
        fireEvent.dragStart(rows[1], { dataTransfer: dt });
        fireEvent.dragOver(rows[0], { dataTransfer: dt });
        fireEvent.drop(rows[0], { dataTransfer: dt });
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        rs = s.last();
        expect(rs.criteria.filter((c) => c.readiness_pillar_id === 10).sort((a, b) => a.sort_order - b.sort_order).map((c) => c.code)).toEqual(["a2", "a1"]);
    });

    it("edits a criterion in the side drawer, including its type config", () => {
        const s = saved();
        render(<RuleSetBuilder ruleSet={draft} onSave={s.onSave} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("button", { name: "تعديل نوع الحسابات" }));
        const drawer = screen.getByTestId("criterion-drawer");
        expect(within(drawer).getAllByTestId("choice-option")).toHaveLength(2);
        fireEvent.click(within(drawer).getByRole("button", { name: "إضافة خيار" }));
        fireEvent.change(within(drawer).getByLabelText("القيمة", { selector: "#op-v-2" }), { target: { value: "z" } });
        fireEvent.change(within(drawer).getByLabelText("النص", { selector: "#op-l-2" }), { target: { value: "لا توجد" } });
        fireEvent.change(within(drawer).getByLabelText("النقاط (0-1)", { selector: "#op-p-2" }), { target: { value: "0" } });
        fireEvent.click(within(drawer).getByLabelText(/شرط إلزامي/));
        fireEvent.click(within(drawer).getByRole("button", { name: "حفظ المعيار" }));
        expect(screen.queryByTestId("criterion-drawer")).toBeNull();
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        const c = s.last().criteria.find((x) => x.code === "b1")!;
        expect(c.is_mandatory).toBe(true);
        expect(c.type === "choice" && c.config.options.at(-1)).toEqual({ value: "z", label_ar: "لا توجد", points: 0 });
    });

    it("adds a new criterion with an empty weight and switches its type", () => {
        const s = saved();
        render(<RuleSetBuilder ruleSet={draft} onSave={s.onSave} previewFetcher={null} />);
        fireEvent.click(within(screen.getAllByTestId("pillar-card")[1]).getByRole("button", { name: /إضافة معيار/ }));
        const drawer = screen.getByTestId("criterion-drawer");
        expect((within(drawer).getByLabelText("الوزن داخل المحور") as HTMLInputElement).value).toBe("");
        fireEvent.change(within(drawer).getByLabelText("الرمز"), { target: { value: "b3" } });
        fireEvent.change(within(drawer).getByLabelText("الاسم"), { target: { value: "مدة السداد" } });
        fireEvent.change(within(drawer).getByLabelText("نوع المعيار"), { target: { value: "numeric_bands" } });
        fireEvent.click(within(drawer).getByRole("button", { name: "إضافة شريحة" }));
        fireEvent.click(within(drawer).getByRole("button", { name: "حفظ المعيار" }));
        expect(screen.getByTestId("validation").textContent).toMatch(/وزن المعيار «مدة السداد»/);
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        const c = s.last().criteria.find((x) => x.code === "b3")!;
        expect(c).toMatchObject({ readiness_pillar_id: 20, type: "numeric_bands", sort_order: 2 });
    });

    it("deletes a pillar and moves its criteria to the implicit pillar", () => {
        const s = saved();
        render(<RuleSetBuilder ruleSet={draft} onSave={s.onSave} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("button", { name: "حذف المحور محور ب" }));
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        expect(s.last().criteria.filter((c) => c.readiness_pillar_id === null).map((c) => c.code).sort()).toEqual(["b1", "b2", "c1"]);
    });

    it("edits score bands with #13 colour tokens and warns about gaps", () => {
        const s = saved();
        render(<RuleSetBuilder ruleSet={draft} onSave={s.onSave} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("tab", { name: "شرائح النتيجة" }));
        const rows = screen.getAllByTestId("band-row");
        expect(rows).toHaveLength(3);
        fireEvent.change(within(rows[2]).getByLabelText("من"), { target: { value: "85" } });
        expect(screen.getByTestId("validation").textContent).toMatch(/فجوة بين 80 و85/);
        fireEvent.click(within(rows[0]).getByRole("radio", { name: "رمادي (محايد)" }));
        fireEvent.click(screen.getByRole("button", { name: "حفظ المسودة" }));
        expect(s.last().bands.map((b) => b.color_token)).toEqual(["muted", "warning", "success"]);
    });

    it("is read-only for a published (frozen) version and offers duplicate", () => {
        const onDuplicate = vi.fn();
        render(<RuleSetBuilder ruleSet={{ ...draft, status: "published" }} onSave={vi.fn()} onDuplicate={onDuplicate} previewFetcher={null} />);
        expect(screen.getByText(/معتمدة ومجمّدة/)).toBeTruthy();
        expect(screen.queryByRole("button", { name: "حفظ المسودة" })).toBeNull();
        expect(screen.queryByRole("button", { name: /إضافة محور/ })).toBeNull();
        expect(screen.queryByTestId("import-input")).toBeNull();
        expect((screen.getAllByLabelText("الوزن")[0] as HTMLInputElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole("button", { name: "عرض سجل تجاري" }));
        expect((within(screen.getByTestId("criterion-drawer")).getByLabelText("الرمز") as HTMLInputElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole("button", { name: /نسخ كمسودة جديدة/ }));
        expect(onDuplicate).toHaveBeenCalledTimes(1);
    });

    it("imports JSON as a draft and reports publish issues", async () => {
        render(<RuleSetBuilder ruleSet={{ ...draft, pillars: [], criteria: [], bands: [] }} previewFetcher={null} />);
        const { exportRuleSet } = await import("./scoring");
        const exp = exportRuleSet(draft);
        exp.rule_set.bands = exp.rule_set.bands.slice(0, 2);
        const file = new File([JSON.stringify(exp)], "rules.json", { type: "application/json" });
        await act(async () => { fireEvent.change(screen.getByTestId("import-input"), { target: { files: [file] } }); });
        expect(await screen.findByTestId("import-result")).toBeTruthy();
        expect(screen.getByTestId("import-result").textContent).toMatch(/ملاحظة/);
        expect(screen.getAllByTestId("pillar-card")).toHaveLength(3);
        expect(screen.getByTestId("validation").textContent).toMatch(/إلى 100 غير مغطى/);
    });

    it("rejects an invalid JSON file", async () => {
        render(<RuleSetBuilder ruleSet={draft} previewFetcher={null} />);
        const file = new File(["not json"], "x.json", { type: "application/json" });
        await act(async () => { fireEvent.change(screen.getByTestId("import-input"), { target: { files: [file] } }); });
        expect(await screen.findByText("الملف ليس JSON صالحاً.")).toBeTruthy();
    });
});
