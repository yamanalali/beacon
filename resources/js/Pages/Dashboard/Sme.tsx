import { Link, usePage } from "@inertiajs/react";
import { QuickLinks } from "../../Components/Common/QuickLinks";
import { visibleLinks } from "../../Layouts/navigationModel";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { QuickRecordButton } from "../../Components/QuickRecord/QuickRecord";
import { StatCard } from "../../Components/Common/StatCard";
import { CurrencyCards } from "../../Components/Dashboard/CurrencyCards";
import { Money } from "../../Components/Common/Money";
import { changeLabel, currencyName, currencySymbol, formatMoneyIsolated } from "../../lib/money";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";
import type { DashboardAlert, SmeComplianceItem, SmeDashboardProps, SmeRecentTransaction } from "../../types/dashboard";

/**
 * Simple SME personal dashboard (§40). Summarizes existing information only; no new metrics or scoring.
 * STUB: each area renders an empty state until its owning module (finance/compliance/readiness/referral) exposes data.
 */
export type SummaryItem = { id: string | number; title: string; meta?: string };

type Props = Partial<SmeDashboardProps>;

const ALERT_TONE: Record<DashboardAlert["severity"], StatusTone> = {
    danger: "danger",
    warning: "warning",
    info: "neutral",
};

/** Alert row: links only when the server gives an href (null = plain text). */
export function AlertItem({ alert }: { alert: DashboardAlert }) {
    const body = (
        <>
            <span>{alert.title}</span>
            <StatusBadge tone={ALERT_TONE[alert.severity]} label={String(alert.count)} />
        </>
    );
    const cls = "list-group-item px-0 d-flex justify-content-between align-items-center gap-2";
    return alert.href ? (
        <Link href={alert.href} className={`${cls} list-group-item-action`}>
            {body}
        </Link>
    ) : (
        <div className={cls}>{body}</div>
    );
}

const money = formatMoneyIsolated;

function TransactionRow({ t }: { t: SmeRecentTransaction }) {
    return (
        <li className="list-group-item px-0">
            <Link href={t.url} className="d-flex justify-content-between gap-2 text-reset text-decoration-none">
                <span>
                    <span className="d-block fw-semibold">{t.category}</span>
                    <small className="text-body-secondary">
                        {t.counterparty ? `${t.counterparty} · ` : ""}
                        <bdi dir="ltr">{t.transaction_date}</bdi>
                    </small>
                </span>
                <span className={t.direction === "income" ? "text-success fw-semibold" : "fw-semibold"}>
                    <Money amount={t.amount} currency={t.currency} signed={t.direction === "income" ? "in" : "out"} />
                </span>
            </Link>
        </li>
    );
}

function DeadlineList({ items, empty }: { items: SmeComplianceItem[]; empty: string }) {
    if (items.length === 0) return <EmptyState title={empty} icon="bi-calendar-check" />;
    return (
        <ul className="list-group list-group-flush">
            {items.map((d) => (
                <li key={d.id} className="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                    <span>{d.title}</span>
                    <span className="d-flex align-items-center gap-2">
                        <bdi dir="ltr" className="text-body-secondary small">{d.due_date}</bdi>
                        {d.overdue ? <StatusBadge tone="danger" label="متأخر" /> : null}
                    </span>
                </li>
            ))}
        </ul>
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
    const can = usePage().props.auth?.can;
    return (
        <AppLayout title="الرئيسية">
            <div className="app-page">
                <PageHeader
                    title="ملخص منشأتك"
                    description="آخر النشاطات وما يحتاج إجراء منك."
                />
                <div className="d-grid d-lg-flex mb-4">
                    <QuickRecordButton />
                </div>
                {enterprise === null ? (
                    <SectionCard>
                        <EmptyState
                            icon="bi-buildings"
                            title="لا توجد منشأة مرتبطة بحسابك بعد"
                            description="تواصل مع فريق Beacon لربط حسابك بمنشأتك."
                        />
                    </SectionCard>
                ) : (
                    <>
                        {alerts.length > 0 ? (
                            <SectionCard title="يحتاج انتباهك">
                                <div className="list-group list-group-flush" data-testid="alerts">
                                    {alerts.map((a) => (
                                        <AlertItem key={a.key} alert={a} />
                                    ))}
                                </div>
                            </SectionCard>
                        ) : null}
                        {kpis && (kpis.by_currency?.current_month?.length ?? 0) > 0 ? (
                            <CurrencyCards kpis={kpis} />
                        ) : kpis ? (
                            <section aria-label="مؤشرات هذا الشهر">
                                <div className="row g-3">
                                    <div className="col-6 col-lg-3">
                                        <StatCard label="دخل هذا الشهر" value={money(kpis.current_month.income, kpis.currency)} hint={changeLabel(kpis.current_month.income, kpis.previous_month.income)} icon="bi-arrow-down-left" />
                                    </div>
                                    <div className="col-6 col-lg-3">
                                        <StatCard label="مصروف هذا الشهر" value={money(kpis.current_month.expense, kpis.currency)} hint={changeLabel(kpis.current_month.expense, kpis.previous_month.expense)} icon="bi-arrow-up-right" tone="amber" />
                                    </div>
                                    <div className="col-6 col-lg-3">
                                        <StatCard label="الصافي" value={money(kpis.current_month.net, kpis.currency)} hint={`${kpis.current_month.count} معاملة هذا الشهر`} icon="bi-graph-up" tone="blue" />
                                    </div>
                                    <div className="col-6 col-lg-3">
                                        <StatCard label="فواتير بانتظار المراجعة" value={String(kpis.pending_extractions_count)} hint={`إجمالي المعاملات: ${kpis.transaction_count}`} icon="bi-receipt" tone="violet" />
                                    </div>
                                </div>
                                {kpis.other_currencies.length > 0 ? (
                                    <p className="small text-body-secondary mt-2 mb-0" data-testid="currency-note">
                                        <i className="bi bi-info-circle me-1" aria-hidden="true" />
                                        هذه الأرقام تشمل فقط المعاملات المسجّلة بـ{currencyName(kpis.currency)} (<bdi>{currencySymbol(kpis.currency)}</bdi>). لديك معاملات أخرى
                                        بعملات مختلفة لم تُحتسب هنا:{" "}
                                        {kpis.other_currencies.map((c, i) => (
                                            <span key={c}>
                                                {i > 0 ? "، " : ""}
                                                {currencyName(c)} (<bdi>{currencySymbol(c)}</bdi>)
                                            </span>
                                        ))}
                                        .
                                    </p>
                                ) : null}
                            </section>
                        ) : null}
                        <div className="row g-3">
                            <div className="col-12 col-lg-6">
                                <SectionCard title="آخر المعاملات">
                                    {recent_transactions.length === 0 ? (
                                        <EmptyState title="لا توجد معاملات بعد" icon="bi-wallet2" />
                                    ) : (
                                        <ul className="list-group list-group-flush">
                                            {recent_transactions.map((t) => (
                                                <TransactionRow key={t.public_id} t={t} />
                                            ))}
                                        </ul>
                                    )}
                                </SectionCard>
                            </div>
                            <div className="col-12 col-lg-6">
                                <SectionCard title="مواعيد الامتثال">
                                    <DeadlineList
                                        items={[...compliance.overdue, ...compliance.upcoming]}
                                        empty="لا توجد مواعيد قريبة"
                                    />
                                </SectionCard>
                            </div>
                            <div className="col-12">
                                <SectionCard title="الجاهزية والإحالة">
                                    <p className="mb-1">
                                        آخر درجة جاهزية:{" "}
                                        <strong>
                                            <bdi dir="ltr">{readinessStatus ?? "غير متاحة بعد"}</bdi>
                                        </strong>
                                    </p>
                                    {referralStatus ? (
                                        <p className="mb-0">
                                            حالة الإحالة: <strong>{referralStatus}</strong>
                                        </p>
                                    ) : null}
                                </SectionCard>
                            </div>
                        </div>
                    </>
                )}
                <QuickLinks
                    items={visibleLinks(can, [
                        { href: "/transactions", label: "المعاملات", description: "المبيعات والمشتريات والدفعات", icon: "bi-wallet2" },
                        { href: "/reports", label: "التقارير", description: "فهم وضع منشأتك المالي", icon: "bi-bar-chart-line" },
                        { href: "/documents", label: "المستندات", description: "كل وثائقك في مكان واحد", icon: "bi-folder2-open" },
                    ])}
                />
            </div>
        </AppLayout>
    );
}
