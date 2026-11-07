import { Link } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { QuickRecordButton } from "../../Components/QuickRecord/QuickRecord";
import { Money } from "../../Components/Common/Money";
import { currencySymbol, formatMoneyIsolated } from "../../lib/money";
import { trendLabel } from "../../Components/Dashboard/CurrencyCards";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";
import type { DashboardAlert, SmeDashboardKpis, SmeDashboardProps, SmeRecentTransaction } from "../../types/dashboard";

/**
 * SME finance home (§40). Layout-only hierarchy: net → دخل/مصروف → سجّل معاملة → آخر الحركات → الحالة.
 * Same server props and QuickRecord flow — no new metrics.
 */
export type SummaryItem = { id: string | number; title: string; meta?: string };

type Props = Partial<SmeDashboardProps>;

const ALERT_TONE: Record<DashboardAlert["severity"], StatusTone> = {
    danger: "danger",
    warning: "warning",
    info: "neutral",
};

const ALERT_ICON: Record<DashboardAlert["key"], string> = {
    overdue_compliance: "bi-exclamation-triangle",
    pending_reviews: "bi-receipt",
    open_escalations: "bi-chat-left-text",
    compliance_version_review: "bi-journal-check",
};

/** Alert row: links only when the server gives an href (null = plain text). */
export function AlertItem({ alert }: { alert: DashboardAlert }) {
    const body = (
        <>
            <span className="sme-alert__icon" aria-hidden="true">
                <i className={`bi ${ALERT_ICON[alert.key] ?? "bi-bell"}`} />
            </span>
            <span className="sme-alert__title">{alert.title}</span>
            <StatusBadge tone={ALERT_TONE[alert.severity]} label={String(alert.count)} />
            {alert.href ? <i className="bi bi-chevron-left text-body-secondary" aria-hidden="true" /> : null}
        </>
    );
    const cls = `sme-alert sme-alert--${alert.severity}`;
    return alert.href ? (
        <Link href={alert.href} className={cls}>
            {body}
        </Link>
    ) : (
        <div className={cls}>{body}</div>
    );
}

const money = formatMoneyIsolated;

function typeLabel(txType: SmeRecentTransaction["type"], t: ReturnType<typeof useT>): string {
    const map: Record<SmeRecentTransaction["type"], string> = {
        sale: t("transactions.type.sale_short", undefined, "مبيع"),
        income: t("transactions.type.income_short", undefined, "دخل"),
        purchase: t("transactions.type.purchase_short", undefined, "شراء"),
        expense: t("transactions.type.expense_short", undefined, "مصروف"),
    };
    return map[txType];
}

function transactionTitle(tx: SmeRecentTransaction, t: ReturnType<typeof useT>): string {
    const desc = tx.description?.trim();
    if (desc) return desc;
    const label = tx.category_label?.trim();
    if (label) return label;
    return typeLabel(tx.type, t);
}

function TransactionRow({ tx }: { tx: SmeRecentTransaction }) {
    const t = useT();
    const income = tx.direction === "income";
    return (
        <li>
            <Link href={tx.url} className="sme-tx">
                <span className={`sme-tx__icon ${income ? "is-in" : "is-out"}`} aria-hidden="true">
                    <i className={`bi ${income ? "bi-arrow-down-left" : "bi-arrow-up-right"}`} />
                </span>
                <span className="sme-tx__copy">
                    <span className="sme-tx__title">{transactionTitle(tx, t)}</span>
                    <span className="sme-tx__meta">
                        {typeLabel(tx.type, t)}
                        {tx.counterparty ? ` · ${tx.counterparty}` : ""}
                        {" · "}
                        <bdi dir="ltr">{tx.transaction_date}</bdi>
                    </span>
                </span>
                <span className={`sme-tx__amount ${income ? "is-in" : "is-out"}`}>
                    <Money amount={tx.amount} currency={tx.currency} signed={income ? "in" : "out"} />
                </span>
            </Link>
        </li>
    );
}

function currencyOptions(kpis: SmeDashboardKpis): string[] {
    const fromBreakdown = (kpis.by_currency?.current_month ?? []).map((c) => c.currency);
    if (fromBreakdown.length > 0) {
        const ordered = [...fromBreakdown];
        ordered.sort((a, b) => (a === b ? 0 : a === "SYP" ? -1 : b === "SYP" ? 1 : a.localeCompare(b)));
        return ordered;
    }
    const codes = [kpis.currency, ...kpis.other_currencies];
    return [...new Set(codes.filter(Boolean))];
}

function totalsFor(kpis: SmeDashboardKpis, code: string): {
    net: string;
    income: string;
    expense: string;
    count: number;
    prevNet: string | null;
    prevIncome: string | null;
    prevExpense: string | null;
} {
    const cur = (kpis.by_currency?.current_month ?? []).find((c) => c.currency === code);
    const prev = (kpis.by_currency?.previous_month ?? []).find((c) => c.currency === code);
    if (cur) {
        return {
            net: cur.net,
            income: cur.income_total,
            expense: cur.expense_total,
            count: cur.transaction_count,
            prevNet: prev?.net ?? null,
            prevIncome: prev?.income_total ?? null,
            prevExpense: prev?.expense_total ?? null,
        };
    }
    if (code === kpis.currency) {
        return {
            net: kpis.current_month.net,
            income: kpis.current_month.income,
            expense: kpis.current_month.expense,
            count: kpis.current_month.count,
            prevNet: kpis.previous_month.net,
            prevIncome: kpis.previous_month.income,
            prevExpense: kpis.previous_month.expense,
        };
    }
    return { net: "0.00", income: "0.00", expense: "0.00", count: 0, prevNet: null, prevIncome: null, prevExpense: null };
}

function HomeSkeleton() {
    const t = useT();
    return (
        <div className="sme-home sme-home--loading" aria-busy="true" aria-label={t("common.loading", undefined, "جارٍ التحميل")} data-testid="home-skeleton">
            <div className="sme-skel sme-skel--hero" />
            <div className="sme-skel sme-skel--btn" />
            <div className="sme-skel sme-skel--list" />
            <div className="sme-skel sme-skel--chips" />
        </div>
    );
}

export default function Sme({
    readinessStatus = null,
    referralStatus = null,
    enterprise = null,
    kpis = null,
    recent_transactions = [],
    compliance = { overdue: [], upcoming: [] },
    alerts = [],
}: Props) {
    const t = useT();
    const homeTitle = t("common.home", undefined, "الرئيسية");
    const deadlines = [...compliance.overdue, ...compliance.upcoming].slice(0, 3);
    const enterpriseHref = enterprise ? `/enterprises/${enterprise.public_id}` : null;
    const feed = recent_transactions.slice(0, 5);
    const topDeadline = deadlines[0] ?? null;

    const codes = useMemo(() => (kpis ? currencyOptions(kpis) : []), [kpis]);
    const [currency, setCurrency] = useState<string>(codes[0] ?? "SYP");
    useEffect(() => {
        if (codes.length === 0) return;
        if (!codes.includes(currency)) setCurrency(codes[0]);
    }, [codes, currency]);

    const active = kpis ? totalsFor(kpis, currency) : null;
    const netTone = active ? (Number(active.net) > 0 ? "is-up" : Number(active.net) < 0 ? "is-down" : "") : "";
    const netTrend = active ? trendLabel(active.net, active.prevNet) : null;
    const incomeTrend = active ? trendLabel(active.income, active.prevIncome) : null;
    const expenseTrend = active ? trendLabel(active.expense, active.prevExpense) : null;

    const heroActionRef = useRef<HTMLDivElement>(null);
    const [showFab, setShowFab] = useState(false);
    useEffect(() => {
        const el = heroActionRef.current;
        if (!el || typeof IntersectionObserver === "undefined") return;
        const io = new IntersectionObserver(([entry]) => setShowFab(!entry.isIntersecting), { threshold: 0.15 });
        io.observe(el);
        return () => io.disconnect();
    }, [enterprise, kpis]);

    if (enterprise === null) {
        return (
            <AppLayout title={homeTitle}>
                <div className="app-page sme-home">
                    <SectionCard>
                        <EmptyState
                            icon="bi-buildings"
                            title={t("dashboard.sme.no_enterprise_title", undefined, "لا توجد منشأة مرتبطة بحسابك بعد")}
                            description={t("dashboard.sme.no_enterprise_desc", undefined, "تواصل مع فريق بيكون لربط حسابك بمنشأتك.")}
                        />
                        <div className="sme-primary mt-3">
                            <QuickRecordButton />
                        </div>
                    </SectionCard>
                </div>
            </AppLayout>
        );
    }

    if (kpis === null) {
        return (
            <AppLayout title={homeTitle}>
                <div className="app-page">
                    <HomeSkeleton />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title={homeTitle}>
            <div className="app-page sme-home">
                {/*
                  Mobile: single column (hero → CTA → alerts → feed → status).
                  Desktop (≥992): 12-col — hero 8 + CTA 4; feed 8 + status 4. width:auto, max 1040px.
                */}
                <div className="sme-home__grid">
                    <section className={`sme-hero ${netTone}`} aria-label={t("dashboard.sme.month_indicators", undefined, "مؤشرات هذا الشهر")} data-testid="home-balance">
                        {codes.length > 1 ? (
                            <div className="sme-currency-toggle" role="tablist" aria-label={t("dashboard.sme.currency_tabs", undefined, "العملة")} data-testid="currency-toggle">
                                {codes.map((code) => (
                                    <button
                                        key={code}
                                        type="button"
                                        role="tab"
                                        aria-selected={currency === code}
                                        className={currency === code ? "is-active" : undefined}
                                        onClick={() => setCurrency(code)}
                                    >
                                        <bdi>{currencySymbol(code)}</bdi>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <div className="sme-hero__eyebrow" data-testid="currency-note">
                                <span aria-hidden="true" />
                                <bdi>{currencySymbol(currency)}</bdi>
                            </div>
                        )}

                        <p className="sme-hero__label">{t("dashboard.sme.net_month", undefined, "صافي هذا الشهر")}</p>
                        <p className="sme-hero__net" data-testid="hero-net">
                            <bdi>{money(active!.net, currency)}</bdi>
                        </p>
                        <p className="sme-hero__meta">
                            {netTrend ? (
                                <>
                                    {netTrend}
                                    {" · "}
                                </>
                            ) : null}
                            <bdi dir="ltr">{t("dashboard.sme.transaction_count", { count: active!.count }, `${active!.count} معاملة`)}</bdi>
                        </p>

                        <div className="sme-hero__split">
                            <div className="sme-hero__stat is-in">
                                <span>{t("transactions.income", undefined, "دخل")}</span>
                                <strong>
                                    <bdi>{money(active!.income, currency)}</bdi>
                                </strong>
                                {incomeTrend ? <small>{incomeTrend}</small> : null}
                            </div>
                            <div className="sme-hero__stat is-out">
                                <span>{t("transactions.expense", undefined, "مصروف")}</span>
                                <strong>
                                    <bdi>{money(active!.expense, currency)}</bdi>
                                </strong>
                                {expenseTrend ? <small>{expenseTrend}</small> : null}
                            </div>
                        </div>
                    </section>

                    <div className="sme-primary sme-primary--panel" ref={heroActionRef}>
                        <p className="sme-primary__hint d-none d-lg-block">{t("dashboard.sme.quick_record_hint", undefined, "سجّل مبيعاً أو مصروفاً بسرعة")}</p>
                        <QuickRecordButton />
                    </div>

                    {alerts.length > 0 ? (
                        <section className="sme-block sme-home__alerts" aria-label={t("dashboard.sme.needs_attention", undefined, "يحتاج انتباهك")}>
                            <div className="sme-alerts" data-testid="alerts">
                                {alerts.slice(0, 2).map((a) => (
                                    <AlertItem key={a.key} alert={a} />
                                ))}
                            </div>
                        </section>
                    ) : null}

                    <section className="sme-block sme-feed">
                        <div className="sme-block__head">
                            <h2>{t("dashboard.sme.recent_activity", undefined, "آخر الحركات")}</h2>
                            <Link href="/transactions" className="sme-block__link">
                                {t("dashboard.sme.view_all", undefined, "عرض الكل")}
                            </Link>
                        </div>
                        {feed.length === 0 ? (
                            <div className="sme-feed__empty">
                                <EmptyState
                                    title={t("dashboard.sme.no_transactions_title", undefined, "لا توجد معاملات بعد")}
                                    description={t("dashboard.sme.no_transactions_desc", undefined, "سجّل أول مبيع أو مصروف.")}
                                    icon="bi-wallet2"
                                />
                                <div className="sme-primary">
                                    <QuickRecordButton />
                                </div>
                            </div>
                        ) : (
                            <ul className="sme-tx-list">
                                {feed.map((tx) => (
                                    <TransactionRow key={tx.public_id} tx={tx} />
                                ))}
                            </ul>
                        )}
                    </section>

                    <aside className="sme-home__side" aria-label={t("dashboard.sme.compliance_readiness", undefined, "الامتثال والجاهزية")}>
                        <section className="sme-status">
                            <Link href={enterpriseHref ? `${enterpriseHref}/compliance` : "#"} className="sme-status__chip">
                                <span className="sme-status__chip-label">{t("dashboard.sme.compliance", undefined, "الامتثال")}</span>
                                {topDeadline ? (
                                    <strong className={topDeadline.overdue ? "text-danger" : ""}>
                                        {topDeadline.overdue
                                            ? t("dashboard.sme.overdue", undefined, "متأخر")
                                            : t("dashboard.sme.soon", undefined, "قريب")}
                                    </strong>
                                ) : (
                                    <strong>{t("dashboard.sme.no_deadlines", undefined, "لا مواعيد")}</strong>
                                )}
                            </Link>
                            <Link href={enterpriseHref ? `${enterpriseHref}/readiness` : "#"} className="sme-status__chip">
                                <span className="sme-status__chip-label">{t("dashboard.sme.readiness", undefined, "الجاهزية")}</span>
                                <strong>
                                    <bdi dir="ltr">{readinessStatus ?? "—"}</bdi>
                                </strong>
                            </Link>
                            <Link href={enterpriseHref ? `${enterpriseHref}/referrals` : "#"} className="sme-status__chip">
                                <span className="sme-status__chip-label">{t("dashboard.sme.referral", undefined, "الإحالة")}</span>
                                <strong>{referralStatus ?? t("dashboard.sme.no_referral", undefined, "لا توجد")}</strong>
                            </Link>
                        </section>
                    </aside>
                </div>

                {showFab ? (
                    <div className="sme-fab d-print-none">
                        <QuickRecordButton variant="fab" />
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
