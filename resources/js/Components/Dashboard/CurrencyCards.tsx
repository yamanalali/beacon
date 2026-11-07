import type { CurrencyTotals, SmeDashboardKpis } from "../../types/dashboard";
import { useT } from "../../Hooks/useT";
import { changeLabel, currencyName, currencySymbol, formatMoneyIsolated, NO_PREVIOUS } from "../../lib/money";

/** Server order is kept (default currency first); SYP is pinned first as well. Never converted between currencies. */
export function orderCurrencies(items: CurrencyTotals[]): CurrencyTotals[] {
    return [...items].sort((a, b) => (a.currency === b.currency ? 0 : a.currency === "SYP" ? -1 : b.currency === "SYP" ? 1 : 0));
}

/** Hide the noisy “no previous month” line; only show real % changes. */
export function trendLabel(current: string | number | null | undefined, previous: string | number | null | undefined): string | null {
    const label = changeLabel(current, previous);
    return label === NO_PREVIOUS ? null : label;
}

function CurrencyCard({ cur, prev }: { cur: CurrencyTotals; prev: CurrencyTotals | undefined }) {
    const t = useT();
    const c = cur.currency;
    const symbol = currencySymbol(c);
    const netTone = Number(cur.net) > 0 ? "is-net-up" : Number(cur.net) < 0 ? "is-net-down" : "";
    const trend = trendLabel(cur.net, prev?.net);

    return (
        <article className="sme-cc" data-testid="currency-card" aria-label={`${currencyName(c)} (${symbol})`}>
            <div className="sme-cc__top">
                <span className="sme-cc__badge" aria-hidden="true">
                    {symbol}
                </span>
                <span className="sme-cc__name">
                    <bdi dir="ltr">{symbol}</bdi>
                </span>
                <span className="sme-cc__count">
                    <bdi dir="ltr">{cur.transaction_count}</bdi>{" "}
                    {t("dashboard.sme.transactions_word", undefined, "معاملة")}
                </span>
            </div>

            <p className={`sme-cc__net ${netTone}`}>
                <span className="sme-cc__net-label">{t("dashboard.sme.net_label", undefined, "الصافي")}</span>
                <strong data-testid="cc-net">
                    <bdi dir="ltr">{formatMoneyIsolated(cur.net, c)}</bdi>
                </strong>
            </p>
            {trend ? <span className="sme-cc__trend">{trend}</span> : null}

            <div className="sme-cc__split" role="group" aria-label={t("dashboard.sme.income_expense_aria", undefined, "الدخل والمصروف")}>
                <div className="sme-cc__side is-in">
                    <span>{t("transactions.income", undefined, "دخل")}</span>
                    <strong data-testid="cc-income">
                        <bdi dir="ltr">{formatMoneyIsolated(cur.income_total, c)}</bdi>
                    </strong>
                </div>
                <div className="sme-cc__side is-out">
                    <span>{t("transactions.expense", undefined, "مصروف")}</span>
                    <strong data-testid="cc-expense">
                        <bdi dir="ltr">{formatMoneyIsolated(cur.expense_total, c)}</bdi>
                    </strong>
                </div>
            </div>
        </article>
    );
}

/** Per-currency wallet cards: net first, income|expense in one row. No previous-month spam. */
export function CurrencyCards({ kpis }: { kpis: Pick<SmeDashboardKpis, "by_currency" | "unposted_count"> }) {
    const t = useT();
    const current = orderCurrencies(kpis.by_currency?.current_month ?? []);
    const previous = kpis.by_currency?.previous_month ?? [];
    const unposted = kpis.unposted_count ?? 0;
    return (
        <section aria-label={t("dashboard.sme.currency_month_aria", undefined, "مؤشرات هذا الشهر حسب العملة")} data-testid="currency-cards">
            <div className="sme-cc-list">
                {current.map((cur) => (
                    <CurrencyCard key={cur.currency} cur={cur} prev={previous.find((p) => p.currency === cur.currency)} />
                ))}
            </div>
            {unposted > 0 ? (
                <p className="sme-cc__unposted" data-testid="unposted-line">
                    <i className="bi bi-hourglass-split" aria-hidden="true" />
                    <bdi>{unposted}</bdi> {t("dashboard.sme.unposted_review", undefined, "فواتير ناطرة مراجعة")}
                </p>
            ) : null}
        </section>
    );
}
