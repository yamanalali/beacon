import { Link, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { useT } from "../../Hooks/useT";
import { QuickLinks } from "../../Components/Common/QuickLinks";
import { visibleLinks } from "../../Layouts/navigationModel";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { FilterBar } from "../../Components/Filters/FilterBar";
import {
    SelectFilter,
    type Option,
} from "../../Components/Filters/SelectFilter";
import { DateRangeFilter } from "../../Components/Filters/DateRangeFilter";
import { ExportButton } from "../../Components/Data/ExportButton";
import { StatCard } from "../../Components/Common/StatCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";
import type { BeaconProgrammeData, ReferralStatus } from "../../types/dashboard";

/**
 * Beacon programme dashboard (§38/§39).
 * Programme KPIs, filters, and CSV export come from the server (BeaconDashboardData).
 * SECTION_38 indicator grid stays empty until Beacon defines formulas — React never invents KPIs.
 * TODO: BUSINESS DECISION REQUIRED — exact §38 indicator definitions, targets (§39).
 */
export type Indicator = { key: string; label: string; value: string | null };

type Props = {
    indicators?: Indicator[];
    filters?: Record<string, string>;
    filterOptions?: {
        sectors?: Option[];
        regions?: Option[];
        genders?: Option[];
    };
    exportUrl?: string | null;
    programme?: BeaconProgrammeData | null;
};

const REFERRAL_TONE: Record<ReferralStatus, StatusTone> = {
    draft: "neutral",
    shared: "neutral",
    under_review: "warning",
    info_requested: "warning",
    decided: "success",
    outcome_recorded: "success",
};

/** Programme KPIs from the server; null KPIs (pending business decision) are hidden, never guessed. */
export function ProgrammeOverview({ programme }: { programme: BeaconProgrammeData }) {
    const t = useT();
    const k = programme.kpis;
    const stats: { label: string; value: number | null; tone?: "amber" }[] = [
        { label: programme.scope === "all" ? t("dashboard.beacon.all_enterprises", undefined, "كل المنشآت") : t("dashboard.beacon.assigned_enterprises", undefined, "المنشآت المسندة إليك"), value: k.enterprises_count },
        { label: t("dashboard.beacon.active_enterprises", undefined, "منشآت نشطة"), value: k.active_enterprises_count },
        { label: t("dashboard.beacon.open_escalations", undefined, "استفسارات مفتوحة"), value: k.open_escalations_count },
        { label: t("dashboard.beacon.overdue_compliance", undefined, "مواعيد امتثال متأخرة"), value: k.overdue_compliance_count, tone: "amber" },
    ];
    const visible = stats.filter((s) => s.value !== null);
    return (
        <div className="d-flex flex-column gap-3">
            <section aria-label={t("dashboard.beacon.programme_indicators", undefined, "مؤشرات البرنامج")}>
                <div className="row g-3">
                    {visible.map((s) => (
                        <div key={s.label} className="col-6 col-md-4 col-xl-3">
                            <StatCard label={s.label} value={String(s.value)} tone={s.tone} />
                        </div>
                    ))}
                </div>
            </section>
            <div className="row g-3">
                <div className="col-12 col-lg-5 d-flex flex-column gap-3">
                    <SectionCard title={t("dashboard.beacon.referrals_by_status", undefined, "الإحالات حسب الحالة")}>
                        {k.referrals_by_status.every((r) => r.count === 0) ? (
                            <EmptyState title={t("dashboard.beacon.no_referrals", undefined, "لا توجد إحالات بعد")} icon="bi-send" />
                        ) : (
                            <ul className="list-group list-group-flush">
                                {k.referrals_by_status.map((r) => (
                                    <li key={r.status} className="list-group-item px-0 d-flex justify-content-between">
                                        <span>{r.label}</span>
                                        <strong><bdi dir="ltr">{r.count}</bdi></strong>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                    {k.enterprises_by_readiness_band && k.enterprises_by_readiness_band.length > 0 ? (
                        <SectionCard title={t("dashboard.beacon.by_readiness", undefined, "المنشآت حسب الجاهزية")}>
                            <ul className="list-group list-group-flush" data-testid="readiness-bands">
                                {k.enterprises_by_readiness_band.map((r) => (
                                    <li key={r.band} className="list-group-item px-0 d-flex justify-content-between">
                                        <span>{r.band}</span>
                                        <strong><bdi dir="ltr">{r.count}</bdi></strong>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    ) : null}
                </div>
                <div className="col-12 col-lg-7">
                    <SectionCard title={t("dashboard.beacon.recent_referrals", undefined, "آخر الإحالات")}>
                        {programme.recent_referrals.length === 0 ? (
                            <EmptyState title={t("dashboard.beacon.no_recent_referrals", undefined, "لا توجد إحالات حديثة")} icon="bi-send" />
                        ) : (
                            <ul className="list-group list-group-flush">
                                {programme.recent_referrals.map((r) => (
                                    <li key={r.public_id} className="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                        <span>
                                            <span className="d-block fw-semibold">{r.enterprise.business_name ?? t("dashboard.beacon.unknown_enterprise", undefined, "منشأة غير معروفة")}</span>
                                            <small className="text-body-secondary">
                                                {r.institution ?? t("dashboard.beacon.no_institution", undefined, "بدون جهة")}
                                                {r.updated_at ? (
                                                    <>
                                                        {" · "}
                                                        <bdi dir="ltr">{r.updated_at.slice(0, 10)}</bdi>
                                                    </>
                                                ) : null}
                                            </small>
                                        </span>
                                        <StatusBadge tone={REFERRAL_TONE[r.status] ?? "neutral"} label={r.status_label} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}

function useSection38() {
    const t = useT();
    return useMemo(
        (): { group: string; items: [string, string][] }[] => [
            {
                group: t("dashboard.beacon.group.enterprises", undefined, "المنشآت والاستخدام"),
                items: [
                    ["onboarding", t("dashboard.beacon.indicator.onboarding", undefined, "تسجيل المنشآت")],
                    ["usage", t("dashboard.beacon.indicator.usage", undefined, "استخدام المنصة")],
                    ["progress", t("dashboard.beacon.indicator.progress", undefined, "تقدّم المنشآت")],
                    ["outstanding", t("dashboard.beacon.indicator.outstanding", undefined, "الإجراءات المعلّقة")],
                ],
            },
            {
                group: t("dashboard.beacon.group.finance", undefined, "السجلات المالية والتقارير"),
                items: [
                    ["completeness", t("dashboard.beacon.indicator.completeness", undefined, "اكتمال السجلات المالية")],
                    ["reporting", t("dashboard.beacon.indicator.reporting", undefined, "نشاط التقارير")],
                ],
            },
            {
                group: t("dashboard.beacon.group.compliance", undefined, "الامتثال والجاهزية"),
                items: [
                    ["compliance", t("dashboard.beacon.indicator.compliance", undefined, "حالة الامتثال")],
                    ["deadlines", t("dashboard.beacon.indicator.deadlines", undefined, "مواعيد امتثال قريبة")],
                    ["readiness", t("dashboard.beacon.indicator.readiness", undefined, "درجات الجاهزية")],
                    ["assessments", t("dashboard.beacon.indicator.assessments", undefined, "سجل التقييمات")],
                ],
            },
            {
                group: t("dashboard.beacon.group.support", undefined, "التدريب والدعم الفني"),
                items: [
                    ["training", t("dashboard.beacon.indicator.training", undefined, "نشاط التدريب")],
                    ["ta", t("dashboard.beacon.indicator.ta", undefined, "نشاط الدعم الفني")],
                ],
            },
            {
                group: t("dashboard.beacon.group.participation", undefined, "المشاركة"),
                items: [
                    ["gender", t("dashboard.beacon.indicator.gender", undefined, "حسب الجنس")],
                    ["geography", t("dashboard.beacon.indicator.geography", undefined, "حسب المنطقة")],
                    ["sector", t("dashboard.beacon.indicator.sector", undefined, "حسب القطاع")],
                ],
            },
            {
                group: t("dashboard.beacon.group.financing", undefined, "التمويل والإحالات"),
                items: [
                    ["referrals", t("dashboard.beacon.indicator.referrals", undefined, "إحالات التمويل")],
                    ["decisions", t("dashboard.beacon.indicator.decisions", undefined, "قرارات الإحالة")],
                    ["outcomes", t("dashboard.beacon.indicator.outcomes", undefined, "نتائج التمويل")],
                ],
            },
        ],
        [t],
    );
}

export default function Beacon({
    indicators = [],
    filters = {},
    filterOptions = {},
    exportUrl = null,
    programme = null,
}: Props) {
    const t = useT();
    const section38 = useSection38();
    const can = usePage().props.auth?.can;
    const byKey = new Map(indicators.map((i) => [i.key, i.value]));
    return (
        <AppLayout title={t("dashboard.beacon.monitoring", undefined, "لوحة المتابعة")}>
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title={t("dashboard.beacon.title", undefined, "لوحة متابعة البرنامج")}
                        description={t("dashboard.beacon.description", undefined, "مؤشرات المتابعة والتقييم لبرنامج Beacon.")}
                    />
                    {exportUrl ? <ExportButton baseUrl={exportUrl} formats={["csv"]} /> : null}
                </div>
                <div className="app-overview-banner">
                    <div className="d-flex align-items-center gap-3">
                        <span className="app-overview-banner__icon">
                            <i className="bi bi-compass" aria-hidden="true" />
                        </span>
                        <div>
                            <h2>{t("dashboard.beacon.banner_title", undefined, "رؤية شاملة، وخطوة تالية أوضح")}</h2>
                            <p>{t("dashboard.beacon.banner_body", undefined, "تابع المنشآت والجاهزية والتمويل من مساحة عمل واحدة.")}</p>
                        </div>
                    </div>
                    <Link href="/enterprises" className="btn btn-primary">
                        {t("dashboard.beacon.browse_enterprises", undefined, "استعراض المنشآت")}{" "}
                        <i
                            className="bi bi-arrow-left ms-2"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
                {programme ? <ProgrammeOverview programme={programme} /> : null}
                <QuickLinks
                    items={visibleLinks(can, [
                        {
                            href: "/enterprises",
                            label: t("nav.enterprises", undefined, "المنشآت"),
                            description: t("dashboard.beacon.link.enterprises", undefined, "الملفات ومتابعة التقدّم"),
                            icon: "bi-buildings",
                        },
                        {
                            href: "/transactions",
                            label: t("dashboard.beacon.link.transactions", undefined, "السجلات المالية"),
                            description: t("dashboard.beacon.link.transactions_desc", undefined, "المعاملات والدفعات"),
                            icon: "bi-wallet2",
                        },
                        {
                            href: "/documents",
                            label: t("nav.documents", undefined, "المستندات"),
                            description: t("dashboard.beacon.link.documents_desc", undefined, "الوثائق والإيصالات"),
                            icon: "bi-folder2-open",
                        },
                    ])}
                />
                <FilterBar
                    initial={{
                        from: "",
                        to: "",
                        sector: "",
                        region: "",
                        gender: "",
                        ...filters,
                    }}
                >
                    {(v, set) => (
                        <>
                            <div className="col-12 col-lg-4">
                                <DateRangeFilter
                                    id="period"
                                    value={{ from: v.from, to: v.to }}
                                    onChange={(r) => {
                                        set("from", r.from);
                                        set("to", r.to);
                                    }}
                                />
                            </div>
                            <div className="col-12 col-sm-4 col-lg-2">
                                <SelectFilter
                                    id="sector"
                                    label={t("smes.sector", undefined, "القطاع")}
                                    value={v.sector}
                                    options={filterOptions.sectors ?? []}
                                    onChange={(x) => set("sector", x)}
                                />
                            </div>
                            <div className="col-12 col-sm-4 col-lg-2">
                                <SelectFilter
                                    id="region"
                                    label={t("dashboard.beacon.region", undefined, "المنطقة")}
                                    value={v.region}
                                    options={filterOptions.regions ?? []}
                                    onChange={(x) => set("region", x)}
                                />
                            </div>
                            <div className="col-12 col-sm-4 col-lg-2">
                                <SelectFilter
                                    id="gender"
                                    label={t("dashboard.beacon.gender", undefined, "الجنس")}
                                    value={v.gender}
                                    options={filterOptions.genders ?? []}
                                    onChange={(x) => set("gender", x)}
                                />
                            </div>
                        </>
                    )}
                </FilterBar>
                {indicators.length === 0 ? (
                    <SectionCard title={t("dashboard.beacon.indicators_section", undefined, "مؤشرات المتابعة والتقييم")}>
                        <EmptyState
                            icon="bi-clipboard-data"
                            title={t("dashboard.beacon.indicators_pending", undefined, "لم تُعتمد مؤشرات المتابعة بعد")}
                            description={t("dashboard.beacon.indicators_pending_hint", undefined, "ستظهر هنا عند اعتماد تعريفات المؤشرات.")}
                        />
                    </SectionCard>
                ) : (
                <div className="app-indicator-groups">
                    {section38.map((g) => (
                        <SectionCard key={g.group} title={g.group}>
                            <div className="row g-2">
                                {g.items.map(([key, label]) => {
                                    const value = byKey.get(key) ?? null;
                                    return (
                                        <div
                                            key={key}
                                            className={
                                                g.items.length === 2
                                                    ? "col-12"
                                                    : "col-6"
                                            }
                                        >
                                            <div className="app-indicator">
                                                <div className="app-indicator__label">
                                                    {label}
                                                </div>
                                                <div className="app-indicator__value">
                                                    <bdi dir="ltr">
                                                        {value ?? "—"}
                                                    </bdi>
                                                </div>
                                                {value === null ? (
                                                    <div className="app-indicator__note">
                                                        {t("dashboard.beacon.indicator_pending", undefined, "بانتظار تعريف المؤشر")}
                                                    </div>
                                                ) : null}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </SectionCard>
                    ))}
                </div>
                )}
            </div>
        </AppLayout>
    );
}
