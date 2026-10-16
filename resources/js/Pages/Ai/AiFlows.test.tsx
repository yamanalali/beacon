import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { request, reload, get } = vi.hoisted(() => ({
    request: vi.fn(),
    reload: vi.fn(),
    get: vi.fn(),
}));
vi.mock("axios", () => ({
    default: {
        create: () => ({ request }),
        isAxiosError: (e: { isAxiosError?: boolean }) => e.isAxiosError,
    },
}));
vi.mock("../../Layouts/AppLayout", () => ({
    default: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
vi.mock("@inertiajs/react", () => ({
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: ReactNode;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: { reload, get },
}));
import Assistant from "./Assistant";
import Knowledge from "./Knowledge/Index";
import Published from "./Knowledge/Published";
import Notifications from "../Notifications/Index";

const empty = {
    data: [],
    meta: { current_page: 1, last_page: 1, total: 0, from: null, to: null },
    links: {},
};
beforeEach(() => {
    request.mockReset();
    reload.mockReset();
    get.mockReset();
});
afterEach(cleanup);

describe("assistant and follow-up", () => {
    it("offers team escalation while AI is unavailable and sends the entered question", async () => {
        request.mockResolvedValue({ data: { id: "public-escalation" } });
        render(
            <Assistant
                ai_available={false}
                can_escalate
                question_max_length={2000}
            />,
        );
        expect(
            screen.queryByRole("button", { name: "اسأل المساعد" }),
        ).toBeNull();
        fireEvent.change(screen.getByLabelText("سؤالك"), {
            target: { value: "كيف أسجل فاتورة؟" },
        });
        fireEvent.click(
            screen.getByRole("button", { name: "إرسال لفريق بيكون" }),
        );
        await screen.findByRole("link", { name: "متابعة الاستفسار" });
        expect(request).toHaveBeenCalledWith({
            method: "post",
            url: "/ai/escalations",
            data: { question: "كيف أسجل فاتورة؟" },
        });
        expect(
            screen
                .getByRole("link", { name: "متابعة الاستفسار" })
                .getAttribute("href"),
        ).toBe("/ai/pages/escalations/public-escalation");
    });
    it("shows the answer as text and links its approved sources", async () => {
        request.mockResolvedValue({
            data: {
                supported: true,
                ai_generated: true,
                label: "اقتراح للمراجعة",
                answer: "<script>unsafe</script>",
                sources: [{ id: "K1", title: "دليل المبيعات" }],
                can_escalate: true,
            },
        });
        const { container } = render(
            <Assistant ai_available can_escalate question_max_length={2000} />,
        );
        fireEvent.change(screen.getByLabelText("سؤالك"), {
            target: { value: "كيف أسجل المبيعات؟" },
        });
        fireEvent.click(screen.getByRole("button", { name: "اسأل المساعد" }));
        await screen.findByText("<script>unsafe</script>");
        expect(container.querySelector("script")).toBeNull();
        expect(
            screen
                .getByRole("link", { name: "دليل المبيعات" })
                .getAttribute("href"),
        ).toContain("search=");
    });
    it("keeps the question and offers retry after a rate limit error", async () => {
        request.mockRejectedValue({
            isAxiosError: true,
            response: { status: 429 },
        });
        render(
            <Assistant ai_available can_escalate question_max_length={2000} />,
        );
        fireEvent.change(screen.getByLabelText("سؤالك"), {
            target: { value: "سؤال عن الامتثال" },
        });
        fireEvent.click(screen.getByRole("button", { name: "اسأل المساعد" }));
        await screen.findByRole("alert");
        expect(
            (screen.getByLabelText("سؤالك") as HTMLTextAreaElement).value,
        ).toBe("سؤال عن الامتثال");
        expect(
            (
                screen.getByRole("button", {
                    name: "اسأل المساعد",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(false);
    });
});

it("creates knowledge as a draft through the real endpoint contract", async () => {
    request.mockResolvedValue({ data: { public_id: "K1", status: "draft" } });
    render(
        <Knowledge
            articles={empty}
            topics={["bookkeeping"]}
            statuses={["draft", "published", "archived"]}
            filters={{ status: null, topic: null }}
            can_manage
        />,
    );
    fireEvent.click(screen.getByRole("button", { name: "إرشاد جديد" }));
    fireEvent.change(screen.getByLabelText("العنوان"), {
        target: { value: "دليل جديد" },
    });
    fireEvent.change(screen.getByLabelText("نص الإرشاد"), {
        target: { value: "نص معتمد للمراجعة" },
    });
    fireEvent.click(screen.getByRole("button", { name: "حفظ" }));
    await waitFor(() =>
        expect(reload).toHaveBeenCalledWith({ only: ["articles"] }),
    );
    expect(request).toHaveBeenCalledWith(
        expect.objectContaining({
            method: "post",
            url: "/ai/admin/knowledge",
            data: {
                title: "دليل جديد",
                body: "نص معتمد للمراجعة",
                topic: "bookkeeping",
                effective_from: null,
                effective_until: null,
            },
        }),
    );
});

it("renders approved knowledge with readable plain text", () => {
    render(
        <Published
            articles={{
                ...empty,
                data: [
                    {
                        id: "K1",
                        title: "الإرشاد",
                        body: "<b>نص</b>",
                        topic: "bookkeeping",
                        version: 2,
                        effective_from: null,
                        effective_until: null,
                    },
                ],
            }}
            topics={["bookkeeping"]}
            filters={{ topic: null, search: null }}
            can_manage={false}
        />,
    );
    expect(screen.getByText("<b>نص</b>")).toBeTruthy();
    expect(screen.queryByRole("link", { name: "إدارة المحتوى" })).toBeNull();
});

it("marks only the selected notification read and refreshes its count", async () => {
    request.mockResolvedValue({ data: { id: "N1", read_at: "2026-10-16" } });
    render(
        <Notifications
            notifications={{
                ...empty,
                data: [
                    {
                        id: "N1",
                        data: {
                            type: "compliance_deadline",
                            title: "موعد قريب",
                            message: "راجع المتطلب",
                            enterprise_public_id: "E1",
                        },
                        read_at: null,
                        created_at: "2026-10-16",
                    },
                ],
            }}
            unread_count={1}
            filters={{ filter: null }}
        />,
    );
    fireEvent.click(screen.getByRole("button", { name: "تحديد كمقروء" }));
    await waitFor(() =>
        expect(reload).toHaveBeenCalledWith({
            only: ["notifications", "unread_count"],
        }),
    );
    expect(request).toHaveBeenCalledWith(
        expect.objectContaining({
            url: "/notifications/N1/read",
            method: "post",
        }),
    );
    expect(
        screen
            .getByRole("link", { name: "متابعة الامتثال" })
            .getAttribute("href"),
    ).toBe("/enterprises/E1/compliance");
});
