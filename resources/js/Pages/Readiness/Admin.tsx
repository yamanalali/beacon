import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type Criterion = { id: number; code: string; label_ar: string; weight: number; is_mandatory: boolean; missing_data_treatment_label: string; compliance_requirement_code: string | null };
type RuleSet = { id: number; version_number: number; name_ar: string; status: string; status_label: string; change_summary: string | null; published_at: string | null; criteria: Criterion[] };
type Props = { ruleSets: RuleSet[]; treatments: Record<string, string>; complianceRequirements: { id: number; code: string }[] };

function CriterionForm({ ruleSet, treatments, complianceRequirements }: { ruleSet: RuleSet } & Omit<Props, "ruleSets">) {
    const form = useForm({ code: "", label_ar: "", weight: "", is_mandatory: false, missing_data_treatment: "", compliance_requirement_id: "" });

    return (
        <form
            className="border-top pt-3 mt-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/admin/readiness/${ruleSet.id}/criteria`, { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <TextInput id={`code-${ruleSet.id}`} name="code" label="رمز المعيار" value={form.data.code} onChange={(v) => form.setData("code", v)} error={form.errors.code} />
            <TextInput id={`label-${ruleSet.id}`} name="label_ar" label="اسم المعيار" value={form.data.label_ar} onChange={(v) => form.setData("label_ar", v)} error={form.errors.label_ar} />
            <TextInput id={`weight-${ruleSet.id}`} name="weight" type="number" label="الوزن" value={form.data.weight} onChange={(v) => form.setData("weight", v)} error={form.errors.weight} />
            <div className="mb-3">
                <label className="form-label mb-1 fw-semibold" htmlFor={`treat-${ruleSet.id}`}>معاملة البيانات الناقصة</label>
                <select id={`treat-${ruleSet.id}`} className="form-select" style={{ minHeight: "48px" }} value={form.data.missing_data_treatment} onChange={(e) => form.setData("missing_data_treatment", e.target.value)}>
                    <option value="">اختر حسب قرار بيكون</option>
                    {Object.entries(treatments).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                </select>
            </div>
            <div className="mb-3">
                <label className="form-label mb-1 fw-semibold" htmlFor={`req-${ruleSet.id}`}>مرتبط بمتطلب امتثال (اختياري)</label>
                <select id={`req-${ruleSet.id}`} className="form-select" style={{ minHeight: "48px" }} value={form.data.compliance_requirement_id} onChange={(e) => form.setData("compliance_requirement_id", e.target.value)}>
                    <option value="">بدون</option>
                    {complianceRequirements.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}
                </select>
            </div>
            <div className="form-check mb-3">
                <input id={`mand-${ruleSet.id}`} type="checkbox" className="form-check-input" checked={form.data.is_mandatory} onChange={(e) => form.setData("is_mandatory", e.target.checked)} />
                <label htmlFor={`mand-${ruleSet.id}`} className="form-check-label">شرط إلزامي</label>
            </div>
            <button type="submit" className="btn btn-outline-primary" style={{ minHeight: "48px" }}>إضافة معيار</button>
        </form>
    );
}

export default function Admin({ ruleSets, treatments, complianceRequirements }: Props) {
    const form = useForm({ name_ar: "", change_summary: "", copy_from_current: true });

    return (
        <AppLayout title="قواعد الجاهزية">
            <div className="app-page">
                <PageHeader title="قواعد تقييم الجاهزية" description="المعايير والأوزان تُعتمد من بيكون. النسخ المنشورة لا تُعدّل." />
                <SectionCard title="نسخة قواعد جديدة">
                    <form onSubmit={(e) => { e.preventDefault(); form.post("/admin/readiness", { preserveScroll: true }); }}>
                        <TextInput id="name_ar" name="name_ar" label="الاسم" value={form.data.name_ar} onChange={(v) => form.setData("name_ar", v)} error={form.errors.name_ar} />
                        <TextInput id="change_summary" name="change_summary" label="ملخص التغيير" value={form.data.change_summary} onChange={(v) => form.setData("change_summary", v)} />
                        <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }}>إنشاء مسودة</button>
                    </form>
                </SectionCard>
                <div className="mt-4 d-flex flex-column gap-3">
                    {ruleSets.length === 0 ? <EmptyState title="لا توجد قواعد بعد. بانتظار اعتماد بيكون." /> : null}
                    {ruleSets.map((set) => (
                        <article key={set.id} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <strong>{set.name_ar} (نسخة {set.version_number})</strong>
                                <StatusBadge label={set.status_label} tone={set.status === "published" ? "success" : "warning"} />
                            </div>
                            <ul className="mt-2">
                                {set.criteria.map((c) => (
                                    <li key={c.id}>
                                        {c.label_ar} - الوزن {c.weight}{c.is_mandatory ? " - إلزامي" : ""} - {c.missing_data_treatment_label}
                                        {set.status === "draft" ? (
                                            <button type="button" className="btn btn-link btn-sm" onClick={() => router.delete(`/admin/readiness/criteria/${c.id}`, { preserveScroll: true })}>حذف</button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                            {set.status === "draft" ? (
                                <>
                                    <button type="button" className="btn btn-primary" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/readiness/${set.id}/publish`, {}, { preserveScroll: true })}>اعتماد ونشر</button>
                                    <CriterionForm ruleSet={set} treatments={treatments} complianceRequirements={complianceRequirements} />
                                </>
                            ) : null}
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
