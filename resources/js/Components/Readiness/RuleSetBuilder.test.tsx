import { act, cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({ router: { reload: vi.fn(), post: vi.fn() } }));

import type { ApiResult, BuilderActions } from "./adminApi";
import { RuleSetBuilder } from "./RuleSetBuilder";
import { draft } from "./fixtures.test-data";

afterEach(() => cleanup());

const ok: ApiResult = { ok: true };
const fail = (message: string, errors: Record<string, string> = {}): ApiResult => ({ ok: false, message, errors });

function mockActions(over: Partial<BuilderActions> = {}): BuilderActions {
    const f = () => vi.fn().mockResolvedValue(ok);
    return {
        savePillar: f(), deletePillar: f(), reorderPillars: f(), saveCriterion: f(), deleteCriterion: f(), reorderCriteria: f(),
        saveBand: f(), deleteBand: f(), reorderBands: f(), duplicate: f(), importFile: f(), publish: f(),
        exportUrl: (rs) => `/admin/readiness/${rs.id}/export`, ...over,
    };
}

describe("RuleSetBuilder wired to the admin API", () => {
    it("renders pillars, criteria and the implicit pillar with no warnings for a valid set", () => {
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions()} previewFetcher={null} />);
        const cards = screen.getAllByTestId("pillar-card");
        expect(cards).toHaveLength(3);
        expect(within(cards[0]).getAllByTestId("criterion-row")).toHaveLength(2);
        expect(cards[2].textContent).toContain("معايير غير مصنفة");
        expect(screen.queryByTestId("validation")).toBeNull();
        expect(screen.getByRole("link", { name: /تصدير JSON/ }).getAttribute("href")).toBe("/admin/readiness/1/export");
    });

    it("shows the effective equal-share weights (implicit pillar = average of set weights)", () => {
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions()} previewFetcher={null} />);
        expect(screen.getByText(/الأوزان الفعلية/).textContent).toContain("2 / 1");
    });

    it("saves a pillar on blur and shows 422 field errors inline", async () => {
        const savePillar = vi.fn().mockResolvedValue(fail("راجع الحقول المعلّمة.", { code: "الرمز مستخدم." }));
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions({ savePillar })} previewFetcher={null} />);
        const card = screen.getAllByTestId("pillar-card")[0];
        const input = within(card).getByLabelText("الرمز");
        fireEvent.change(input, { target: { value: "B" } });
        fireEvent.blur(input);
        await waitFor(() => expect(within(card).getByRole("alert").textContent).toBe("الرمز مستخدم."));
        expect(savePillar.mock.calls[0][1]).toMatchObject({ id: 10, code: "B" });
    });

    it("does not call the server on blur when nothing changed", () => {
        const savePillar = vi.fn().mockResolvedValue(ok);
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions({ savePillar })} previewFetcher={null} />);
        fireEvent.blur(within(screen.getAllByTestId("pillar-card")[0]).getByLabelText("الرمز"));
        expect(savePillar).not.toHaveBeenCalled();
    });

    it("creates a new pillar without a weight (equal share) and posts it", async () => {
        const actions = mockActions();
        render(<RuleSetBuilder ruleSet={draft} actions={actions} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("button", { name: /إضافة محور/ }));
        const card = screen.getAllByTestId("pillar-card")[2];
        fireEvent.change(within(card).getByLabelText("الرمز"), { target: { value: "C" } });
        fireEvent.change(within(card).getByLabelText("اسم المحور"), { target: { value: "محور ج" } });
        expect(screen.queryByText(/وزن المحور/)).toBeNull();
        await act(async () => { fireEvent.click(within(card).getByRole("button", { name: "حفظ المحور" })); });
        expect((actions.savePillar as ReturnType<typeof vi.fn>).mock.calls[0][1]).toMatchObject({ code: "C", label_ar: "محور ج", weight: "" });
    });

    it("reorders pillars and criteria with the full id order", async () => {
        const actions = mockActions();
        render(<RuleSetBuilder ruleSet={draft} actions={actions} previewFetcher={null} />);
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "نقل محور ب للأعلى" })); });
        expect(actions.reorderPillars).toHaveBeenCalledWith(expect.anything(), [20, 10]);
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "نقل أشهر السجلات للأعلى" })); });
        expect(actions.reorderCriteria).toHaveBeenCalledWith(expect.anything(), [3, 4, 2, 1, 5]);
    });

    it("keeps the drawer open with server errors when saving a criterion fails", async () => {
        const saveCriterion = vi.fn().mockResolvedValue(fail("راجع الحقول المعلّمة.", { weight: "وزن المعيار يجب أن يكون أكبر من صفر." }));
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions({ saveCriterion })} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("button", { name: "تعديل سجل تجاري" }));
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "حفظ المعيار" })); });
        expect(screen.getByText("وزن المعيار يجب أن يكون أكبر من صفر.")).toBeTruthy();
        expect(saveCriterion.mock.calls[0][1]).toMatchObject({ id: 1, code: "a1" });
    });

    it("disables publish without bands using the server message", () => {
        const publish = vi.fn();
        render(<RuleSetBuilder ruleSet={{ ...draft, bands: [] }} actions={mockActions({ publish })} previewFetcher={null} />);
        const btn = screen.getByRole("button", { name: "اعتماد ونشر" }) as HTMLButtonElement;
        expect(btn.disabled).toBe(true);
        expect(btn.title).toBe("لا يمكن نشر القواعد بدون نطاقات تصنيف.");
        expect(screen.getByTestId("validation").textContent).toContain("لا يمكن نشر القواعد بدون نطاقات تصنيف.");
    });

    it("publishes and shows a server error when it is rejected", async () => {
        const publish = vi.fn().mockResolvedValue(fail("راجع الحقول المعلّمة.", { bands: "لا يمكن نشر القواعد بدون نطاقات تصنيف." }));
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions({ publish })} previewFetcher={null} />);
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "اعتماد ونشر" })); });
        expect(publish).toHaveBeenCalled();
        expect(screen.getByTestId("builder-notice").textContent).toContain("لا يمكن نشر القواعد بدون نطاقات تصنيف.");
    });

    it("shows the server message when duplicating while a draft is open (422)", async () => {
        const duplicate = vi.fn().mockResolvedValue(fail("توجد مسودة قواعد غير منشورة."));
        render(<RuleSetBuilder ruleSet={{ ...draft, status: "published" }} actions={mockActions({ duplicate })} previewFetcher={null} />);
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: /نسخ كمسودة جديدة/ })); });
        expect(screen.getByTestId("builder-notice").textContent).toContain("توجد مسودة قواعد غير منشورة.");
        expect(screen.queryByRole("button", { name: "اعتماد ونشر" })).toBeNull();
    });

    it("validates the JSON client-side before uploading, then shows the server import error", async () => {
        const importFile = vi.fn().mockResolvedValue(fail("توجد مسودة قواعد غير منشورة."));
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions({ importFile })} previewFetcher={null} />);
        const input = screen.getByTestId("import-input");
        const bad = new File(["{"], "bad.json", { type: "application/json" });
        await act(async () => { fireEvent.change(input, { target: { files: [bad] } }); });
        await waitFor(() => expect(screen.getByTestId("builder-notice")).toBeTruthy());
        expect(importFile).not.toHaveBeenCalled();
        const { exportRuleSet } = await import("./scoring");
        const good = new File([JSON.stringify(exportRuleSet(draft))], "rules.json", { type: "application/json" });
        await act(async () => { fireEvent.change(input, { target: { files: [good] } }); });
        await waitFor(() => expect(importFile).toHaveBeenCalledWith(good));
        await waitFor(() => expect(screen.getByTestId("builder-notice").textContent).toContain("توجد مسودة قواعد غير منشورة."));
    });

    it("saves a band row and shows its field error", async () => {
        const saveBand = vi.fn().mockResolvedValue(fail("راجع الحقول المعلّمة.", { max: "الحد الأعلى غير صالح." }));
        render(<RuleSetBuilder ruleSet={draft} actions={mockActions({ saveBand })} previewFetcher={null} />);
        fireEvent.click(screen.getByRole("tab", { name: "شرائح النتيجة" }));
        fireEvent.change(screen.getByLabelText(/اسم الشريحة|الاسم/, { selector: "#band-label-0" }), { target: { value: "ضعيفة" } });
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "حفظ الشريحة 1" })); });
        expect(saveBand.mock.calls[0][1]).toMatchObject({ id: 1, label_ar: "ضعيفة" });
        expect(screen.getByText("الحد الأعلى غير صالح.")).toBeTruthy();
    });

    it("is read-only for a published set", () => {
        render(<RuleSetBuilder ruleSet={{ ...draft, status: "published" }} actions={mockActions()} previewFetcher={null} />);
        expect(screen.queryByRole("button", { name: /إضافة محور/ })).toBeNull();
        expect(screen.getByText(/مجمّدة ولا يمكن تعديلها/)).toBeTruthy();
    });
});
