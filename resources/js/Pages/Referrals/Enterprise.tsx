import { Link, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type Summary = { public_id: string; institution: string | null; status: string; status_label: string; created_at: string | null };
type Props = {
    enterprise: { public_id: string; business_name: string };
    referrals: Summary[];
    canCreate: boolean;
    institutions: { id: number; name_ar: string }[];
    assessments: { id: number; score: string | null; finalized_at: string | null }[];
    disclosableItems: Record<string, string>;
};

export default function Enterprise({ enterprise, referrals, canCreate, institutions, assessments, disclosableItems }: Props) {
    const form = useForm<{ mfi_institution_id: string; purpose_ar: string; disclosed_items: string[]; readiness_assessment_id: string }>({
        mfi_institution_id: "",
        purpose_ar: "",
        disclosed_items: [],
        readiness_assessment_id: "",
    });
    const toggle = (key: string) =>
        form.setData("disclosed_items", form.data.disclosed_items.includes(key) ? form.data.disclosed_items.filter((k) => k !== key) : [...form.data.disclosed_items, key]);

    return (
        <AppLayout title="الإحالات">
            <div className="app-page">
                <PageHeader title="الإحالات إلى مؤسسات التمويل" description={enterprise.business_name} />
                {canCreate ? (
                    <SectionCard title="تحضير إحالة">
                        <form onSubmit={(e) => { e.preventDefault(); form.post(`/enterprises/${enterprise.public_id}/referrals`); }}>
                            <select aria-label="المؤسسة" className="form-select mb-2" style={{ minHeight: "48px" }} value={form.data.mfi_institution_id} onChange={(e) => form.setData("mfi_institution_id", e.target.value)}>
                                <option value="">اختر المؤسسة</option>
                                {institutions.map((i) => <option key={i.id} value={i.id}>{i.name_ar}</option>)}
                            </select>
                            {form.errors.mfi_institution_id ? <p className="text-danger">{form.errors.mfi_institution_id}</p> : null}
                            <TextInput id="purpose_ar" name="purpose_ar" label="الغرض" value={form.data.purpose_ar} onChange={(v) => form.setData("purpose_ar", v)} error={form.errors.purpose_ar} />
                            <fieldset className="mb-2">
                                <legend className="fs-6 fw-semibold">المعلومات التي ستُشارك</legend>
                                {Object.entries(disclosableItems).map(([k, l]) => (
                                    <div className="form-check" key={k}>
                                        <input id={`item-${k}`} type="checkbox" className="form-check-input" checked={form.data.disclosed_items.includes(k)} onChange={() => toggle(k)} />
                                        <label htmlFor={`item-${k}`} className="form-check-label">{l}</label>
                                    </div>
                                ))}
                            </fieldset>
                            {form.data.disclosed_items.includes("readiness_assessment") ? (
                                <select aria-label="التقييم" className="form-select mb-2" style={{ minHeight: "48px" }} value={form.data.readiness_assessment_id} onChange={(e) => form.setData("readiness_assessment_id", e.target.value)}>
                                    <option value="">اختر تقييماً نهائياً</option>
                                    {assessments.map((a) => <option key={a.id} value={a.id}>{a.finalized_at} - {a.score ?? "بدون نتيجة"}</option>)}
                                </select>
                            ) : null}
                            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>حفظ كمسودة</button>
                        </form>
                    </SectionCard>
                ) : null}
                <div className="mt-4 d-flex flex-column gap-3">
                    {referrals.length === 0 ? <EmptyState title="لا توجد إحالات" /> : null}
                    {referrals.map((r) => (
                        <Link key={r.public_id} href={`/referrals/${r.public_id}`} className="border rounded p-3 bg-white text-decoration-none text-body d-flex justify-content-between flex-wrap gap-2">
                            <span>{r.institution} - {r.created_at}</span>
                            <StatusBadge label={r.status_label} tone="neutral" />
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
