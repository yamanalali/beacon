import type { CurrencyTotals, SmeDashboardKpis } from "../../types/dashboard";
import { changeLabel, currencyName, currencySymbol, formatMoneyIsolated } from "../../lib/money";

/** Server order is kept (default currency first); SYP is pinned first as well. Never converted between currencies. */
export function orderCurrencies(items: CurrencyTotals[]): CurrencyTotals[] {
    return [...items].sort((a, b) => (a.currency === b.currency ? 0 : a.currency === "SYP" ? -1 : b.currency === "SYP" ? 1 : 0));
}

const netColor = (net: string) => (Number(net) > 0 ? "var(--app-success)" : Number(net) < 0 ? "var(--app-danger)" : undefined);

function CurrencyCard({ cur, prev }: { cur: CurrencyTotals; prev: CurrencyTotals | undefined }) {
    const c = cur.currency;
    const line = (label: string, value: string, previous: string | undefined, testId: string, color?: string) => (
        <div className="app-record-line">
            <span>{label}</span>
            <span className="text-end">
                <bdi dir="ltr" data-testid={testId} style={color ? { color, fontWeight: 600 } : undefined}>{formatMoneyIsolated(value, c)}</bdi>
                <span className="d-block small text-body-secondary">{changeLabel(value, previous ?? "0")}</span>
            </span>
        </div>
    );
    return (
        <article className="app-mobile-record h-100" data-testid="currency-card" aria-label={currencyName(c)}>
            <div className="d-flex justify-content-between align-items-center mb-1">
                <span className="fw-semibold">{currencyName(c)}</span>
                <bdi className="text-body-secondary">{currencySymbol(c)}</bdi>
            </div>
            {line("الدخل", cur.income_total, prev?.income_total, "cc-income")}
            {line("المصروف", cur.expense_total, prev?.expense_total, "cc-expense")}
            {line("الصافي", cur.net, prev?.net, "cc-net", netColor(cur.net))}
            <div className="app-record-line"><span>عدد المعاملات</span><bdi dir="ltr">{cur.transaction_count}</bdi></div>
        </article>
    );
}

/** Per-currency KPI cards: this month vs last month, plus the unposted transactions line (hidden when 0). */
export function CurrencyCards({ kpis }: { kpis: Pick<SmeDashboardKpis, "by_currency" | "unposted_count"> }) {
    const current = orderCurrencies(kpis.by_currency?.current_month ?? []);
    const previous = kpis.by_currency?.previous_month ?? [];
    const unposted = kpis.unposted_count ?? 0;
    return (
        <section aria-label="مؤشرات هذا الشهر حسب العملة" data-testid="currency-cards">
            <div className="row g-3">
                {current.map((cur) => (
                    <div key={cur.currency} className="col-12 col-md-6 col-xl-4">
                        <CurrencyCard cur={cur} prev={previous.find((p) => p.currency === cur.currency)} />
                    </div>
                ))}
            </div>
            {unposted > 0 ? (
                <p className="small text-body-secondary mt-2 mb-0" data-testid="unposted-line">
                    <i className="bi bi-hourglass-split me-1" aria-hidden="true" />
                    <bdi>{unposted}</bdi> فواتير ناطرة مراجعة
                </p>
            ) : null}
        </section>
    );
}
