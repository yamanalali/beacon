import { act, cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

const reload = vi.fn();
const get = vi.fn();
vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children, preserveScroll: _p, ...rest }: { href: string; children: ReactNode; preserveScroll?: boolean }) => <a href={href} {...rest}>{children}</a>,
    usePage: () => ({ props: { auth: {} } }),
    router: { reload: (...a: unknown[]) => reload(...a), get: (...a: unknown[]) => get(...a), visit: vi.fn() },
}));
vi.mock("../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));

import Assistant from "./Assistant";
import EscIndex from "./Escalations/Index";
import EscShow from "./Escalations/Show";
import Published from "./Knowledge/Published";
import KbIndex from "./Knowledge/Index";
import Notifications from "../Notifications/Index";
import { postJson } from "../../Components/Ai/http";

afterEach(() => { cleanup(); reload.mockReset(); get.mockReset(); });

const json = (status: number, body: unknown) => Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));
const page = <T,>(data: T[]) => ({ data, links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length, from: data.length ? 1 : null, to: data.length || null, links: [] } });

describe("postJson", () => {
    it("sends XSRF + JSON and maps 422/429/network", async () => {
        document.cookie = "XSRF-TOKEN=t%3D";
        const f = vi.fn(() => json(200, { a: 1 }));
        expect(await postJson("/x", { q: 1 }, f as unknown as typeof fetch)).toEqual({ ok: true, status: 200, data: { a: 1 } });
        const init = (f.mock.calls[0] as unknown as [string, RequestInit])[1];
        expect((init.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBe("t=");
        expect(init.body).toBe('{"q":1}');
        expect(await postJson("/x", {}, (() => json(422, { errors: { question: ["قصير"] } })) as unknown as typeof fetch)).toMatchObject({ ok: false, message: "قصير" });
        expect(await postJson("/x", {}, (() => json(429, {})) as unknown as typeof fetch)).toMatchObject({ ok: false, status: 429 });
        expect(await postJson("/x", {}, (() => Promise.reject(new Error("x"))) as unknown as typeof fetch)).toMatchObject({ ok: false, status: 0 });
    });
});

it.each(["<html>Login</html>", "null", "[]", "invalid response"])("rejects unusable successful API bodies: %s", async body => {
    expect(await postJson("/x", {}, vi.fn().mockResolvedValue(new Response(body)))).toMatchObject({ ok: false });
});

describe("Assistant", () => {
    it("asks, shows the AI label, answer and sources, then escalates", async () => {
        const f = vi.fn((url: string) =>
            url === "/ai/assistant/ask"
                ? json(200, { supported: true, ai_generated: true, label: "إجابة مولّدة آلياً", answer: "سجّل كل فاتورة.", sources: [{ id: "K1", title: "أساسيات الدفاتر" }], can_escalate: true })
                : json(201, { id: "ESC1", status: "open", message: "تم إرسال سؤالك إلى فريق بيكون." }),
        );
        render(<Assistant ai_available can_escalate question_max_length={2000} fetchImpl={f as unknown as typeof fetch} />);
        const ask = screen.getByRole("button", { name: /اسأل المساعد/ }) as HTMLButtonElement;
        expect(ask.disabled).toBe(true);
        fireEvent.change(screen.getByLabelText("اكتب سؤالك"), { target: { value: "كيف أسجل فاتورة؟" } });
        await act(async () => { fireEvent.click(ask); });
        expect(screen.getByText("إجابة مولّدة آلياً")).toBeTruthy();
        expect(screen.getByText("أساسيات الدفاتر")).toBeTruthy();
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: /أرسل السؤال/ })); });
        expect(screen.getByRole("link", { name: "عرض الاستفسار" }).getAttribute("href")).toBe("/ai/pages/escalations/ESC1");
    });

    it("when AI is unavailable offers only escalation", () => {
        render(<Assistant ai_available={false} can_escalate question_max_length={2000} />);
        expect(screen.getByText(/المساعد غير متاح/)).toBeTruthy();
        expect(screen.queryByRole("button", { name: /اسأل المساعد/ })).toBeNull();
        expect(screen.getByRole("button", { name: /أرسل السؤال/ })).toBeTruthy();
    });

    it("shows server errors", async () => {
        render(<Assistant ai_available can_escalate question_max_length={2000} fetchImpl={(() => json(429, {})) as unknown as typeof fetch} />);
        fireEvent.change(screen.getByLabelText("اكتب سؤالك"), { target: { value: "سؤال طويل" } });
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: /اسأل المساعد/ })); });
        expect(screen.getByRole("alert").textContent).toMatch(/طلبات كثيرة/);
    });
});

describe("Escalations", () => {
    it("index lists with status badges, filter chips and empty state", () => {
        const { unmount } = render(<EscIndex escalations={page([{ id: "E1", question: "سؤال الضريبة", status: "open", created_at: "2026-10-16T10:00:00+03:00" }])} filters={{}} can_escalate />);
        expect(screen.getAllByRole("link", { name: "سؤال الضريبة" })[0].getAttribute("href")).toBe("/ai/pages/escalations/E1");
        expect(screen.getAllByText("مفتوح").length).toBeGreaterThan(0);
        expect(within(screen.getByRole("navigation", { name: "تصفية حسب الحالة" })).getByText("الكل").getAttribute("aria-current")).toBe("true");
        unmount();
        render(<EscIndex escalations={page([])} filters={{ status: "open" }} can_escalate={false} />);
        expect(screen.getByText("لا توجد استفسارات بهذه الحالة")).toBeTruthy();
    });

    it("show renders the question and enterprise link only when present", () => {
        const { unmount } = render(<EscShow escalation={{ id: "E1", question: "س", status: "open", created_at: null, enterprise_id: "ENT1" }} />);
        expect(screen.getByRole("link", { name: "عرض المنشأة" }).getAttribute("href")).toBe("/enterprises/ENT1");
        unmount();
        render(<EscShow escalation={{ id: "E1", question: "س", status: "open", created_at: null, enterprise_id: null }} />);
        expect(screen.queryByRole("link", { name: "عرض المنشأة" })).toBeNull();
    });
});

describe("Knowledge", () => {
    const art = { id: "K1", title: "أساسيات الدفاتر", body: "نص", topic: "bookkeeping", version: 2, effective_from: "2026-01-01", effective_until: null };
    it("published shows topic labels, searches via router.get and has an empty state", () => {
        const { unmount } = render(<Published articles={page([art])} topics={["bookkeeping"]} filters={{}} can_manage ai_available />);
        expect(screen.getAllByText("مسك الدفاتر").length).toBe(2);
        expect(screen.getByRole("link", { name: "إدارة المقالات" })).toBeTruthy();
        fireEvent.change(screen.getByLabelText("ابحث في المقالات"), { target: { value: "فاتورة" } });
        fireEvent.submit(screen.getByRole("search"));
        expect(get).toHaveBeenCalledWith("/ai/pages/knowledge", { topic: undefined, search: "فاتورة" }, expect.anything());
        unmount();
        render(<Published articles={page([])} topics={[]} filters={{ search: "x" }} can_manage={false} ai_available={false} />);
        expect(screen.getByText("لا توجد مقالات مطابقة")).toBeTruthy();
    });

    it("admin publishes a draft and reloads", async () => {
        const f = vi.fn(() => json(200, {}));
        render(<KbIndex articles={page([{ ...art, status: "draft", published_at: null, updated_at: null }])} topics={["bookkeeping"]} statuses={["draft", "published", "archived"]} filters={{}} can_manage fetchImpl={f as unknown as typeof fetch} />);
        await act(async () => { fireEvent.click(screen.getAllByRole("button", { name: "نشر" })[0]); });
        expect((f.mock.calls[0] as unknown as [string])[0]).toBe("/ai/admin/knowledge/K1/publish");
        expect(reload).toHaveBeenCalled();
        expect(screen.getByText("تم نشر المقال.")).toBeTruthy();
    });

    it("admin create form shows validation errors", async () => {
        render(<KbIndex articles={page([])} topics={["bookkeeping"]} statuses={["draft"]} filters={{}} can_manage fetchImpl={(() => json(422, { errors: { title: ["العنوان مطلوب"] } })) as unknown as typeof fetch} />);
        fireEvent.click(screen.getByRole("button", { name: /مقال جديد/ }));
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "حفظ كمسودة" })); });
        expect(screen.getAllByText("العنوان مطلوب").length).toBeGreaterThan(0);
    });
});

describe("Notifications", () => {
    const n = { id: "11111111-1111-1111-1111-111111111111", data: { type: "compliance_deadline", title: "موعد امتثال قريب", message: "تجديد الرخصة", enterprise_public_id: "ENT1" }, read_at: null, created_at: "2026-10-16T09:00:00+03:00" };
    it("keeps the unread count correct after refreshed server props arrive", async () => {
        const second = { ...n, id: "second" };
        const f = vi.fn(() => json(200, { id: n.id, read_at: "now" }));
        const { rerender } = render(<Notifications notifications={page([n, second])} unread_count={2} filters={{}} fetchImpl={f as unknown as typeof fetch} />);
        await act(async () => { fireEvent.click(screen.getAllByRole("button", { name: "تعليم كمقروء" })[0]); });
        expect(screen.getByText("لديك 1 إشعار غير مقروء.")).toBeTruthy();
        rerender(<Notifications notifications={page([{ ...n, read_at: "now" }, second])} unread_count={1} filters={{}} fetchImpl={f as unknown as typeof fetch} />);
        expect(screen.getByText("لديك 1 إشعار غير مقروء.")).toBeTruthy();
    });
    it("marks a notification read and updates the count", async () => {
        const f = vi.fn(() => json(200, { id: n.id, read_at: "now" }));
        render(<Notifications notifications={page([n])} unread_count={1} filters={{}} fetchImpl={f as unknown as typeof fetch} />);
        expect(screen.getByText("لديك 1 إشعار غير مقروء.")).toBeTruthy();
        expect(screen.getByRole("link", { name: "فتح" }).getAttribute("href")).toBe("/enterprises/ENT1/compliance");
        await act(async () => { fireEvent.click(screen.getByRole("button", { name: "تعليم كمقروء" })); });
        expect((f.mock.calls[0] as unknown as [string])[0]).toBe(`/notifications/${n.id}/read`);
        expect(screen.queryByRole("button", { name: "تعليم كمقروء" })).toBeNull();
        expect(screen.getByText("لا توجد إشعارات غير مقروءة.")).toBeTruthy();
    });
    it("empty state for unread filter", () => {
        render(<Notifications notifications={page([])} unread_count={0} filters={{ filter: "unread" }} />);
        expect(screen.getByText("لا توجد إشعارات غير مقروءة", { selector: "h2" })).toBeTruthy();
    });
});
