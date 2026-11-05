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

const TYPE_LABEL: Record<SmeRecentTransaction["type"], string> = {
    sale: "مبيع",
    income: "دخل",
    purchase: "شراء",
    expense: "مصروف",
};

function transactionTitle(t: SmeRecentTransaction): string {
    const desc = t.description?.trim();
    if (desc) return desc;
    const label = t.category_label?.trim();
    if (label) return label;
    return TYPE_LABEL[t.type];
}

function TransactionRow({ t }: { t: SmeRecentTransaction }) {
    const income = t.direction === "income";
    return (
        <li>
            <Link href={t.url} className="sme-tx">
                <span className={`sme-tx__icon ${income ? "is-in" : "is-out"}`} aria-hidden="true">
                    <i className={`bi ${income ? "bi-arrow-down-left" : "bi-arrow-up-right"}`} />
                </span>
                <span className="sme-tx__copy">
                    <span className="sme-tx__title">{transactionTitle(t)}</span>
                    <span className="sme-tx__meta">
                        {TYPE_LABEL[t.type]}
                        {t.counterparty ? ` · ${t.counterparty}` : ""}
                        {" · "}
                        <bdi dir="ltr">{t.transaction_date}</bdi>
                    </span>
                </span>
                <span className={`sme-tx__amount ${income ? "is-in" : "is-out"}`}>
                    <Money amount={t.amount} currency={t.currency} signed={income ? "in" : "out"} />
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
    return (
        <div className="sme-home sme-home--loading" aria-busy="true" aria-label="جارٍ التحميل" data-testid="home-skeleton">
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
            <AppLayout title="الرئيسية">
                <div className="app-page sme-home">
                    <SectionCard>
                        <EmptyState
                            icon="bi-buildings"
                            title="لا توجد منشأة مرتبطة بحسابك بعد"
                            description="تواصل مع فريق Beacon لربط حسابك بمنشأتك."
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
            <AppLayout title="الرئيسية">
                <div className="app-page">
                    <HomeSkeleton />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="الرئيسية">
            <div className="app-page sme-home">
                {/*
                  Mobile: single column (hero → CTA → alerts → feed → status).
                  Desktop (≥992): 12-col — hero 8 + CTA 4; feed 8 + status 4. width:auto, max 1040px.
                */}
                <div className="sme-home__grid">
                    <section className={`sme-hero ${netTone}`} aria-label="مؤشرات هذا الشهر" data-testid="home-balance">
                        {codes.length > 1 ? (
                            <div className="sme-currency-toggle" role="tablist" aria-label="العملة" data-testid="currency-toggle">
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

                        <p className="sme-hero__label">صافي هذا الشهر</p>
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
                            <bdi dir="ltr">{active!.count}</bdi> معاملة
                        </p>

                        <div className="sme-hero__split">
                            <div className="sme-hero__stat is-in">
                                <span>دخل</span>
                                <strong>
                                    <bdi>{money(active!.income, currency)}</bdi>
                                </strong>
                                {incomeTrend ? <small>{incomeTrend}</small> : null}
                            </div>
                            <div className="sme-hero__stat is-out">
                                <span>مصروف</span>
                                <strong>
                                    <bdi>{money(active!.expense, currency)}</bdi>
                                </strong>
                                {expenseTrend ? <small>{expenseTrend}</small> : null}
                            </div>
                        </div>
                    </section>

                    <div className="sme-primary sme-primary--panel" ref={heroActionRef}>
                        <p className="sme-primary__hint d-none d-lg-block">سجّل مبيعاً أو مصروفاً بسرعة</p>
                        <QuickRecordButton />
                    </div>

                    {alerts.length > 0 ? (
                        <section className="sme-block sme-home__alerts" aria-label="يحتاج انتباهك">
                            <div className="sme-alerts" data-testid="alerts">
                                {alerts.slice(0, 2).map((a) => (
                                    <AlertItem key={a.key} alert={a} />
                                ))}
                            </div>
                        </section>
                    ) : null}

                    <section className="sme-block sme-feed">
                        <div className="sme-block__head">
                            <h2>آخر الحركات</h2>
                            <Link href="/transactions" className="sme-block__link">
                                عرض الكل
                            </Link>
                        </div>
                        {feed.length === 0 ? (
                            <div className="sme-feed__empty">
                                <EmptyState title="لا توجد معاملات بعد" description="سجّل أول مبيع أو مصروف." icon="bi-wallet2" />
                                <div className="sme-primary">
                                    <QuickRecordButton />
                                </div>
                            </div>
                        ) : (
                            <ul className="sme-tx-list">
                                {feed.map((t) => (
                                    <TransactionRow key={t.public_id} t={t} />
                                ))}
                            </ul>
                        )}
                    </section>

                    <aside className="sme-home__side" aria-label="الامتثال والجاهزية">
                        <section className="sme-status">
                            <Link href={enterpriseHref ? `${enterpriseHref}/compliance` : "#"} className="sme-status__chip">
                                <span className="sme-status__chip-label">الامتثال</span>
                                {topDeadline ? (
                                    <strong className={topDeadline.overdue ? "text-danger" : ""}>
                                        {topDeadline.overdue ? "متأخر" : "قريب"}
                                    </strong>
                                ) : (
                                    <strong>لا مواعيد</strong>
                                )}
                            </Link>
                            <Link href={enterpriseHref ? `${enterpriseHref}/readiness` : "#"} className="sme-status__chip">
                                <span className="sme-status__chip-label">الجاهزية</span>
                                <strong>
                                    <bdi dir="ltr">{readinessStatus ?? "—"}</bdi>
                                </strong>
                            </Link>
                            <Link href={enterpriseHref ? `${enterpriseHref}/referrals` : "#"} className="sme-status__chip">
                                <span className="sme-status__chip-label">الإحالة</span>
                                <strong>{referralStatus ?? "لا توجد"}</strong>
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
