import { Link, usePage } from "@inertiajs/react";
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
 * Beacon programme dashboard (§38/§39). STUB: no backend endpoint yet.
 * All values must come from the server (same calculation as exports). React never computes KPIs.
 * TODO: BUSINESS DECISION REQUIRED — exact KPI definitions, reporting periods, filters, formulas, targets (§39).
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
    const k = programme.kpis;
    const stats: { label: string; value: number | null; icon: string; tone: "teal" | "blue" | "amber" | "violet" }[] = [
        { label: programme.scope === "all" ? "كل المنشآت" : "المنشآت المسندة إليك", value: k.enterprises_count, icon: "bi-buildings", tone: "teal" },
        { label: "منشآت نشطة", value: k.active_enterprises_count, icon: "bi-activity", tone: "blue" },
        { label: "استفسارات مفتوحة", value: k.open_escalations_count, icon: "bi-chat-left-dots", tone: "violet" },
        { label: "مواعيد امتثال متأخرة", value: k.overdue_compliance_count, icon: "bi-calendar-x", tone: "amber" },
    ];
    const visible = stats.filter((s) => s.value !== null);
    return (
        <>
            <section aria-label="مؤشرات البرنامج">
                <div className="row g-3">
                    {visible.map((s) => (
                        <div key={s.label} className="col-6 col-lg">
                            <StatCard label={s.label} value={String(s.value)} icon={s.icon} tone={s.tone} />
                        </div>
                    ))}
                </div>
            </section>
            <div className="row g-3">
                <div className="col-12 col-lg-5">
                    <SectionCard title="الإحالات حسب الحالة">
                        {k.referrals_by_status.every((r) => r.count === 0) ? (
                            <EmptyState title="لا توجد إحالات بعد" icon="bi-send" />
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
                        <SectionCard title="المنشآت حسب الجاهزية">
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
                    <SectionCard title="آخر الإحالات">
                        {programme.recent_referrals.length === 0 ? (
                            <EmptyState title="لا توجد إحالات حديثة" icon="bi-send" />
                        ) : (
                            <ul className="list-group list-group-flush">
                                {programme.recent_referrals.map((r) => (
                                    <li key={r.public_id} className="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                        <span>
                                            <span className="d-block fw-semibold">{r.enterprise.business_name ?? "منشأة غير معروفة"}</span>
                                            <small className="text-body-secondary">
                                                {r.institution ?? "بدون جهة"}
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
        </>
    );
}

const SECTION_38: { group: string; items: [string, string][] }[] = [
    {
        group: "المنشآت والاستخدام",
        items: [
            ["onboarding", "تسجيل المنشآت"],
            ["usage", "استخدام المنصة"],
            ["progress", "تقدّم المنشآت"],
            ["outstanding", "الإجراءات المعلّقة"],
        ],
    },
    {
        group: "السجلات المالية والتقارير",
        items: [
            ["completeness", "اكتمال السجلات المالية"],
            ["reporting", "نشاط التقارير"],
        ],
    },
    {
        group: "الامتثال والجاهزية",
        items: [
            ["compliance", "حالة الامتثال"],
            ["deadlines", "مواعيد امتثال قريبة"],
            ["readiness", "درجات الجاهزية"],
            ["assessments", "سجل التقييمات"],
        ],
    },
    {
        group: "التدريب والدعم الفني",
        items: [
            ["training", "نشاط التدريب"],
            ["ta", "نشاط الدعم الفني"],
        ],
    },
    {
        group: "المشاركة",
        items: [
            ["gender", "حسب الجنس"],
            ["geography", "حسب المنطقة"],
            ["sector", "حسب القطاع"],
        ],
    },
    {
        group: "التمويل والإحالات",
        items: [
            ["referrals", "إحالات التمويل"],
            ["decisions", "قرارات الإحالة"],
            ["outcomes", "نتائج التمويل"],
        ],
    },
];

export default function Beacon({
    indicators = [],
    filters = {},
    filterOptions = {},
    exportUrl = null,
    programme = null,
}: Props) {
    const can = usePage().props.auth?.can;
    const byKey = new Map(indicators.map((i) => [i.key, i.value]));
    return (
        <AppLayout title="لوحة المتابعة">
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title="لوحة متابعة البرنامج"
                        description="مؤشرات المتابعة والتقييم لبرنامج Beacon."
                    />
                    {exportUrl ? <ExportButton baseUrl={exportUrl} /> : null}
                </div>
                <div className="app-overview-banner">
                    <div className="d-flex align-items-center gap-3">
                        <span className="app-overview-banner__icon">
                            <i className="bi bi-compass" aria-hidden="true" />
                        </span>
                        <div>
                            <h2>رؤية شاملة، وخطوة تالية أوضح</h2>
                            <p>
                                تابع المنشآت والجاهزية والتمويل من مساحة عمل
                                واحدة.
                            </p>
                        </div>
                    </div>
                    <Link href="/enterprises" className="btn btn-primary">
                        استعراض المنشآت{" "}
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
                            label: "المنشآت",
                            description: "الملفات ومتابعة التقدّم",
                            icon: "bi-buildings",
                        },
                        {
                            href: "/transactions",
                            label: "السجلات المالية",
                            description: "المعاملات والدفعات",
                            icon: "bi-wallet2",
                        },
                        {
                            href: "/documents",
                            label: "المستندات",
                            description: "الوثائق والإيصالات",
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
                                    label="القطاع"
                                    value={v.sector}
                                    options={filterOptions.sectors ?? []}
                                    onChange={(x) => set("sector", x)}
                                />
                            </div>
                            <div className="col-12 col-sm-4 col-lg-2">
                                <SelectFilter
                                    id="region"
                                    label="المنطقة"
                                    value={v.region}
                                    options={filterOptions.regions ?? []}
                                    onChange={(x) => set("region", x)}
                                />
                            </div>
                            <div className="col-12 col-sm-4 col-lg-2">
                                <SelectFilter
                                    id="gender"
                                    label="الجنس"
                                    value={v.gender}
                                    options={filterOptions.genders ?? []}
                                    onChange={(x) => set("gender", x)}
                                />
                            </div>
                        </>
                    )}
                </FilterBar>
                {indicators.length === 0 ? (
                    <SectionCard title="مؤشرات المتابعة والتقييم">
                        <EmptyState
                            icon="bi-clipboard-data"
                            title="لم تُعتمد مؤشرات المتابعة بعد"
                            description="ستظهر هنا عند اعتماد تعريفات المؤشرات."
                        />
                    </SectionCard>
                ) : (
                <div className="app-indicator-groups">
                    {SECTION_38.map((g) => (
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
                                                        بانتظار تعريف المؤشر
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
