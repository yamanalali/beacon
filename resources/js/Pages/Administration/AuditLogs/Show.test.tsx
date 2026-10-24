import { cleanup, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, expect, it, vi } from "vitest";
vi.mock("@inertiajs/react", () => ({ Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a> }));
vi.mock("../../../Layouts/AppLayout", () => ({ default: ({ children }: { children: ReactNode }) => <div>{children}</div> }));
import Show from "./Show";
afterEach(cleanup);
const log = { id: 1, created_at: "2026-10-25", action: "user.role_changed", target_type: "User", target_id: "7", actor_name: "مدير", actor_email: null, context_rows: [] };
it("shows labeled change values in a table and escapes content", () => {
    const { container } = render(<Show log={{ ...log, context_rows: [{ label: "قبل التغيير / الدور", value: "مستخدم المنشأة" }, { label: "العنوان", value: "<script>alert(1)</script>" }] }} />);
    expect(screen.getByRole("table")).toBeTruthy();
    expect(screen.getByText("مستخدم المنشأة")).toBeTruthy();
    expect(container.querySelector("pre, script, textarea")).toBeNull();
    expect(container.textContent).not.toContain("context_json");
});
it("explains empty audit context", () => {
    render(<Show log={log} />);
    expect(screen.getByText("لا توجد تفاصيل إضافية لهذا الإجراء.")).toBeTruthy();
});
