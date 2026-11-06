import { cleanup, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

const visit = vi.fn();
vi.mock("@inertiajs/react", () => ({
    Link: ({ href, children, ...rest }: { href: string; children: ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { visit: (...a: unknown[]) => visit(...a) },
    usePage: () => ({
        url: "/dashboard",
        props: {
            auth: {
                notifications: {
                    unread_count: 2,
                    recent: [
                        {
                            id: "n1",
                            data: { type: "escalation_opened", title: "استفسار جديد", message: "سؤال", escalation_public_id: "ESC1" },
                            read_at: null,
                            created_at: "2026-11-06T10:00:00+03:00",
                        },
                        {
                            id: "n2",
                            data: { type: "compliance_deadline", title: "موعد امتثال", message: "رخصة", enterprise_public_id: "ENT1" },
                            read_at: "2026-11-06T09:00:00+03:00",
                            created_at: "2026-11-06T08:00:00+03:00",
                        },
                    ],
                },
            },
        },
    }),
}));
vi.mock("../Components/Ai/http", () => ({
    postJson: vi.fn(() => Promise.resolve({ ok: true })),
}));

import { NotificationMenu } from "./NotificationMenu";

afterEach(() => {
    cleanup();
    visit.mockReset();
});

describe("NotificationMenu", () => {
    it("shows a red unread badge and links to the notifications page", () => {
        const { container } = render(<NotificationMenu />);
        expect(screen.getByLabelText("الإشعارات، 2 غير مقروء")).toBeTruthy();
        expect(screen.getByText("2")).toBeTruthy();
        const details = container.querySelector("details");
        expect(details).toBeTruthy();
        details!.open = true;
        expect(screen.getByRole("link", { name: "فتح صفحة الإشعارات" }).getAttribute("href")).toBe("/notifications/page");
        expect(screen.getByText("استفسار جديد")).toBeTruthy();
        expect(screen.getByText("موعد امتثال")).toBeTruthy();
    });
});
