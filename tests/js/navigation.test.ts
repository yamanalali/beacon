// Run with: npm run test:nav (Node >= 22 strips TypeScript types natively).
import assert from "node:assert/strict";
import { test } from "node:test";
import {
    groupNavigation, homeLink, iconFor, navigationOrFallback, splitMobile,
} from "../../resources/js/Layouts/navigationModel.ts";

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
    assert.deepEqual(g.map((x) => x.key), ["enterprise", "ai", "account"]);
    assert.deepEqual(g[0].items.map((i) => i.key), ["transactions", "documents", "reports", "readiness"]);
    assert.equal(g[0].label, "مساحة العمل");
    assert.deepEqual(groupNavigation(technical).map((x) => x.key), ["operations", "account"]);
    assert.deepEqual(groupNavigation(technical)[0].items.map((i) => i.key),
        ["operations.security", "operations.backups", "operations.data-control"]);
});

test("renders only what the server sent (no role logic)", () => {
    const keys = groupNavigation(technical).flatMap((g) => g.items.map((i) => i.key));
    assert.ok(!keys.includes("transactions") && !keys.includes("admin"));
    assert.equal(groupNavigation([]).length, 0);
});

test("main group becomes the Home link and is not repeated", () => {
    assert.deepEqual(homeLink(sme, "/transactions"), { href: "/dashboard/sme", active: false });
    assert.deepEqual(homeLink(sme, "/"), { href: "/dashboard/sme", active: true });
    assert.ok(!groupNavigation(sme).some((g) => g.key === "main"));
    assert.equal(homeLink(technical, "/operations/security").href, "/");
});

test("icons by key, then server icon name, then fallback", () => {
    assert.equal(iconFor({ key: "transactions", icon: "whatever" }), "bi-cash-stack");
    assert.equal(iconFor({ key: "new.page", icon: "bell" }), "bi-bell");
    assert.equal(iconFor({ key: "new.page", icon: "unknown" }), "bi-circle");
});

test("unknown groups go last without a heading", () => {
    const g = groupNavigation([item("x", "future"), item("account", "account")]);
    assert.deepEqual(g.map((x) => [x.key, x.label]), [["account", "الحساب"], ["future", null]]);
});

test("mobile: up to 3 tabs by key priority, everything else in More", () => {
    const { tabs, more } = splitMobile(sme);
    assert.deepEqual(tabs.map((i) => i.key), ["transactions", "reports", "documents"]);
    assert.deepEqual(more.map((i) => i.key), ["readiness", "ai.assistant", "notifications", "account"]);
    const t = splitMobile(technical);
    assert.deepEqual(t.tabs.map((i) => i.key), ["operations.security", "operations.backups", "operations.data-control"]);
});

test("fallback when the prop is missing", () => {
    assert.deepEqual(navigationOrFallback(undefined, "/account").map((i) => [i.key, i.active]), [["account", true]]);
    assert.equal(navigationOrFallback([], "/").length, 0);
});
