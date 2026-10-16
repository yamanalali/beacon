import { describe, expect, it, test } from "vitest";
import {
    groupNavigation, homeLink, iconFor, filterByCan, navigationOrFallback, splitMobile,
} from "./navigationModel";

type Item = { key: string; label: string; href: string; icon: string; group: string; active: boolean };
const item = (key: string, group: string, extra: Partial<Item> = {}): Item =>
    ({ key, label: key, href: `/${key}`, icon: "x", group, active: false, ...extra });

const technical: Item[] = [
    item("operations.data-control", "operations"), item("operations.security", "operations"),
    item("operations.backups", "operations"), item("account", "account"), item("account.sessions", "account"),
];
const sme: Item[] = [
    item("dashboard.sme", "main", { href: "/dashboard/sme" }), item("account", "account"),
    item("reports", "enterprise"), item("transactions", "enterprise", { active: true }), item("documents", "enterprise"),
    item("readiness", "enterprise"), item("ai.assistant", "ai"), item("notifications", "account"),
];

test("groups follow fixed group order and stable key order, regardless of server order", () => {
    const g = groupNavigation(sme);
    expect(g.map((x) => x.key)).toEqual(["enterprise", "ai", "account"]);
    expect(g[0].items.map((i) => i.key)).toEqual(["transactions", "documents", "reports", "readiness"]);
    expect(g[0].label).toBe("مساحة العمل");
    expect(groupNavigation(technical).map((x) => x.key)).toEqual(["operations", "account"]);
    expect(groupNavigation(technical)[0].items.map((i) => i.key)).toEqual(["operations.security", "operations.backups", "operations.data-control"]);
});

test("renders only what the server sent (no role logic)", () => {
    const keys = groupNavigation(technical).flatMap((g) => g.items.map((i) => i.key));
    expect(!keys.includes("transactions") && !keys.includes("admin")).toBe(true);
    expect(groupNavigation([]).length).toBe(0);
});

test("main group becomes the Home link and is not repeated", () => {
    expect(homeLink(sme, "/transactions")).toEqual({ href: "/dashboard/sme", active: false });
    expect(homeLink(sme, "/")).toEqual({ href: "/dashboard/sme", active: true });
    expect(groupNavigation(sme).some((g) => g.key === "main")).toBe(false);
    expect(homeLink(technical, "/operations/security").href).toBe("/");
});

test("icons by key, then server icon name, then fallback", () => {
    expect(iconFor({ key: "transactions", icon: "whatever" })).toBe("bi-cash-stack");
    expect(iconFor({ key: "new.page", icon: "bell" })).toBe("bi-bell");
    expect(iconFor({ key: "new.page", icon: "unknown" })).toBe("bi-circle");
});

test("unknown groups go last without a heading", () => {
    const g = groupNavigation([item("x", "future"), item("account", "account")]);
    expect(g.map((x) => [x.key, x.label])).toEqual([["account", "الحساب"], ["future", null]]);
});

test("mobile: up to 3 tabs by key priority, everything else in More", () => {
    const { tabs, more } = splitMobile(sme);
    expect(tabs.map((i) => i.key)).toEqual(["transactions", "reports", "documents"]);
    expect(more.map((i) => i.key)).toEqual(["readiness", "ai.assistant", "notifications", "account"]);
    const t = splitMobile(technical);
    expect(t.tabs.map((i) => i.key)).toEqual(["operations.security", "operations.backups", "operations.data-control"]);
});

test("fallback when the prop is missing", () => {
    expect(navigationOrFallback(undefined, "/account").map((i) => [i.key, i.active])).toEqual([["account", true]]);
    expect(navigationOrFallback([], "/").length).toBe(0);
});

describe("auth.can filtering", () => {
    const mk = (key: string, group = "enterprise") => item(key, group);
    const list = [mk("enterprises"), mk("transactions"), mk("admin.users", "admin"), mk("admin.mfis", "admin")];

    it("hides items whose can flag is false or missing", () => {
        const can = { enterprises: true, transactions: false, "admin.users": true };
        expect(filterByCan(list, can).map((i) => i.key)).toEqual(["enterprises", "admin.users"]);
    });

    it("drops a group that becomes empty", () => {
        const can = { enterprises: true, transactions: true, "admin.users": false, "admin.mfis": false };
        const groups = groupNavigation(navigationOrFallback(list, "/", can)).map((g) => g.key);
        expect(groups).toEqual(["enterprise"]);
    });

    it("keeps the server list when no can map is present", () => {
        expect(navigationOrFallback(list, "/", null)).toHaveLength(4);
    });
});
