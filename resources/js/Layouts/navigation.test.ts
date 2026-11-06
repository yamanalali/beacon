import { describe, expect, it, test } from "vitest";
import {
    groupNavigation, homeLink, iconFor, filterByCan, visibleLinks, navigationOrFallback, splitMobile,
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
    expect(g.map((x) => x.key)).toEqual(["enterprise", "ai"]);
    expect(g[0].items.map((i) => i.key)).toEqual(["transactions", "documents", "reports", "readiness"]);
    expect(g[0].label).toBe("مساحة العمل");
    expect(groupNavigation(technical).map((x) => x.key)).toEqual(["operations"]);
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

test("account group is omitted from sidebar/mobile (topbar menus only)", () => {
    const g = groupNavigation([item("x", "future"), item("account", "account"), item("notifications", "account")]);
    expect(g.map((x) => [x.key, x.label])).toEqual([["future", null]]);
    expect(g.flatMap((x) => x.items.map((i) => i.key))).not.toContain("notifications");
});

test("mobile: 4-tab bar — home priorities leave docs/readiness in More", () => {
    const { tabs, more } = splitMobile(sme, 2);
    expect(tabs.map((i) => i.key)).toEqual(["transactions", "reports"]);
    expect(more.map((i) => i.key)).toEqual(["documents", "readiness", "ai.assistant"]);
    expect(more.map((i) => i.key)).not.toContain("notifications");
    expect(more.map((i) => i.key)).not.toContain("account");
    const t = splitMobile(technical, 2);
    expect(t.tabs.map((i) => i.key)).toEqual(["operations.security", "operations.backups"]);
    expect(t.more.map((i) => i.key)).toContain("operations.data-control");
});

test("mobile: advisor shell prefers escalations over AI assistant", () => {
    const advisor = [
        item("dashboard.beacon", "main"),
        item("enterprises", "enterprise"),
        item("ai.escalations", "ai"),
        item("ai.knowledge", "ai"),
        item("notifications", "account"),
        item("account", "account"),
    ];
    const { tabs, more } = splitMobile(advisor, 2);
    expect(tabs.map((i) => i.key)).toEqual(["enterprises", "ai.escalations"]);
    expect(more.map((i) => i.key)).not.toContain("ai.assistant");
    expect(more.map((i) => i.key)).toContain("ai.knowledge");
    expect(more.map((i) => i.key)).not.toContain("account");
});

test("fallback when the prop is missing", () => {
    expect(navigationOrFallback(undefined, "/account")).toEqual([]);
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

test("dashboard shortcut links follow auth.can", () => {
    const links = [{ href: "/enterprises" }, { href: "/transactions" }, { href: "/documents" }, { href: "/other" }];
    expect(visibleLinks({ enterprises: true, transactions: false, documents: false }, links).map((l) => l.href))
        .toEqual(["/enterprises", "/other"]);
    expect(visibleLinks(undefined, links)).toHaveLength(4);
});
