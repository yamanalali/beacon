import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { FilterBar } from "../../Components/Filters/FilterBar";
import { SelectFilter, type Option } from "../../Components/Filters/SelectFilter";
import { DateRangeFilter } from "../../Components/Filters/DateRangeFilter";
import { ExportButton } from "../../Components/Data/ExportButton";

/**
 * Beacon programme dashboard (§38/§39). STUB: no backend endpoint yet.
 * All values must come from the server (same calculation as exports). React never computes KPIs.
 * TODO: BUSINESS DECISION REQUIRED — exact KPI definitions, reporting periods, filters, formulas, targets (§39).
 */
export type Indicator = { key: string; label: string; value: string | null };

type Props = {
    indicators?: Indicator[];
    filters?: Record<string, string>;
    filterOptions?: { sectors?: Option[]; regions?: Option[]; genders?: Option[] };
    exportUrl?: string | null;
};

const SECTION_38: { group: string; items: [string, string][] }[] = [
    { group: "المنشآت والاستخدام", items: [["onboarding", "تسجيل المنشآت"], ["usage", "استخدام المنصة"], ["progress", "تقدّم المنشآت"], ["outstanding", "الإجراءات المعلّقة"]] },
    { group: "السجلات المالية والتقارير", items: [["completeness", "اكتمال السجلات المالية"], ["reporting", "نشاط التقارير"]] },
    { group: "الامتثال والجاهزية", items: [["compliance", "حالة الامتثال"], ["deadlines", "مواعيد امتثال قريبة"], ["readiness", "درجات الجاهزية"], ["assessments", "سجل التقييمات"]] },
    { group: "التدريب والدعم الفني", items: [["training", "نشاط التدريب"], ["ta", "نشاط الدعم الفني"]] },
    { group: "المشاركة", items: [["gender", "حسب الجنس"], ["geography", "حسب المنطقة"], ["sector", "حسب القطاع"]] },
    { group: "التمويل والإحالات", items: [["referrals", "إحالات التمويل"], ["decisions", "قرارات الإحالة"], ["outcomes", "نتائج التمويل"]] },
];

export default function Beacon({ indicators = [], filters = {}, filterOptions = {}, exportUrl = null }: Props) {
    const byKey = new Map(indicators.map((i) => [i.key, i.value]));
    return (
        <AppLayout title="لوحة المتابعة">
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader title="لوحة متابعة البرنامج" description="مؤشرات المتابعة والتقييم لبرنامج Beacon." />
                    {exportUrl ? <ExportButton baseUrl={exportUrl} /> : null}
                </div>
                <FilterBar initial={{ from: "", to: "", sector: "", region: "", gender: "", ...filters }}>
                    {(v, set) => (
                        <>
                            <div className="col-12 col-lg-4"><DateRangeFilter id="period" value={{ from: v.from, to: v.to }} onChange={(r) => { set("from", r.from); set("to", r.to); }} /></div>
                            <div className="col-12 col-sm-4 col-lg-2"><SelectFilter id="sector" label="القطاع" value={v.sector} options={filterOptions.sectors ?? []} onChange={(x) => set("sector", x)} /></div>
                            <div className="col-12 col-sm-4 col-lg-2"><SelectFilter id="region" label="المنطقة" value={v.region} options={filterOptions.regions ?? []} onChange={(x) => set("region", x)} /></div>
                            <div className="col-12 col-sm-4 col-lg-2"><SelectFilter id="gender" label="الجنس" value={v.gender} options={filterOptions.genders ?? []} onChange={(x) => set("gender", x)} /></div>
                        </>
                    )}
                </FilterBar>
                <div className="d-flex flex-column gap-3">
                    {SECTION_38.map((g) => (
                        <SectionCard key={g.group} title={g.group}>
                            <div className="row g-2">
                                {g.items.map(([key, label]) => {
                                    const value = byKey.get(key) ?? null;
                                    return (
                                        <div key={key} className="col-6 col-md-4 col-xl-3">
                                            <div className="border rounded-3 p-3 h-100">
                                                <div className="small text-body-secondary">{label}</div>
                                                <div className="fs-4 fw-semibold"><bdi dir="ltr">{value ?? "—"}</bdi></div>
                                                {value === null ? <div className="small text-body-secondary">بانتظار تعريف المؤشر</div> : null}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </SectionCard>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}