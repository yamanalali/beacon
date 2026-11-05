import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatCard } from "../../Components/Common/StatCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type Criterion = { id: number; label_ar: string; type_label: string; parameters: Record<string, number> | null; is_active: boolean };
type Institution = { public_id: string; name_ar: string; notes: string | null; is_active: boolean; users: { id: number; name: string }[]; criteria: Criterion[] };
type Props = { institutions: Institution[]; criterionTypes: Record<string, string>; complianceRequirements: { id: number; code: string }[]; mfiUsers: { id: number; name: string; email: string }[] };

function InstitutionCard({ inst, criterionTypes, complianceRequirements, mfiUsers }: { inst: Institution } & Omit<Props, "institutions">) {
    const form = useForm({ label_ar: "", type: "manual_review", min_score: "", compliance_requirement_id: "" });
    const [userId, setUserId] = useState("");
    const activeCriteria = inst.criteria.filter((c) => c.is_active).length;

    return (
        <article className="border rounded p-3 bg-white">
            <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h2 className="h5 mb-1">{inst.name_ar}</h2>
                    {inst.notes ? <p className="small text-body-secondary mb-0">{inst.notes}</p> : null}
                </div>
                <StatusBadge label={inst.is_active ? "فعّالة" : "غير فعّالة"} tone={inst.is_active ? "success" : "warning"} />
            </div>

            <div className="row g-3 mt-1">
                <div className="col-12 col-lg-7">
                    <h3 className="h6">شروط الأهلية <span className="text-body-secondary fw-normal">({activeCriteria} فعّال)</span></h3>
                    {inst.criteria.length === 0 ? <p className="small text-body-secondary">لا توجد شروط بعد. أضف شرطاً ليظهر في المطابقة.</p> : (
                        <ul className="list-unstyled d-flex flex-column gap-2 mb-3">
                            {inst.criteria.map((c) => (
                                <li key={c.id} className="border rounded p-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <span>
                                        <span className="fw-semibold d-block">{c.label_ar}</span>
                                        <span className="small text-body-secondary">{c.type_label}{c.parameters?.min_score !== undefined ? ` · الحد ${c.parameters.min_score}` : ""}</span>
                                    </span>
                                    {c.is_active ? (
                                        <button type="button" className="btn btn-outline-danger btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/mfis/criteria/${c.id}/deactivate`, {}, { preserveScroll: true })}>إيقاف</button>
                                    ) : <StatusBadge label="موقوف" tone="warning" />}
                                </li>
                            ))}
                        </ul>
                    )}
                    <form className="border rounded p-3" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/mfis/${inst.public_id}/criteria`, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                        <p className="fw-semibold mb-2">شرط جديد</p>
                        <TextInput id={`label-${inst.public_id}`} name="label_ar" label="وصف الشرط" value={form.data.label_ar} onChange={(v) => form.setData("label_ar", v)} error={form.errors.label_ar} />
                        <div className="mb-3">
                            <label className="form-label mb-1 fw-semibold" htmlFor={`type-${inst.public_id}`}>نوع الشرط</label>
                            <select id={`type-${inst.public_id}`} className="form-select" style={{ minHeight: "48px" }} value={form.data.type} onChange={(e) => form.setData("type", e.target.value)}>
                                {Object.entries(criterionTypes).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </select>
                        </div>
                        {form.data.type === "min_readiness_score" ? (
                            <TextInput id={`min-${inst.public_id}`} name="min_score" type="number" label="الحد الأدنى" value={form.data.min_score} onChange={(v) => form.setData("min_score", v)} error={form.errors.min_score} />
                        ) : null}
                        {form.data.type === "compliance_requirement_completed" ? (
                            <div className="mb-3">
                                <label className="form-label mb-1 fw-semibold" htmlFor={`req-${inst.public_id}`}>المتطلب</label>
                                <select id={`req-${inst.public_id}`} className="form-select" style={{ minHeight: "48px" }} value={form.data.compliance_requirement_id} onChange={(e) => form.setData("compliance_requirement_id", e.target.value)}>
                                    <option value="">اختر المتطلب</option>
                                    {complianceRequirements.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}
                                </select>
                            </div>
                        ) : null}
                        <button type="submit" className="btn btn-primary" style={{ minHeight: "44px" }} disabled={form.processing}>إضافة شرط</button>
                    </form>
                </div>
                <div className="col-12 col-lg-5">
                    <h3 className="h6">مستخدمو المؤسسة</h3>
                    {inst.users.length === 0 ? <p className="small text-body-secondary">لا يوجد مستخدم مرتبط. بدون ربط لا يرى أحد حزم الإحالة الخاصة بهذه المؤسسة.</p> : (
                        <ul className="list-unstyled d-flex flex-column gap-2 mb-3">
                            {inst.users.map((u) => (
                                <li key={u.id} className="border rounded px-3 py-2">{u.name}</li>
                            ))}
                        </ul>
                    )}
                    <div className="d-flex flex-column gap-2">
                        <label className="form-label mb-0 fw-semibold" htmlFor={`user-${inst.public_id}`}>ربط مستخدم</label>
                        <select id={`user-${inst.public_id}`} className="form-select" style={{ minHeight: "44px" }} value={userId} onChange={(e) => setUserId(e.target.value)}>
                            <option value="">اختر مستخدم مؤسسة تمويل</option>
                            {mfiUsers.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                        </select>
                        <button type="button" className="btn btn-outline-primary" style={{ minHeight: "44px" }} disabled={!userId} onClick={() => router.post(`/admin/mfis/${inst.public_id}/users`, { user_id: userId }, { preserveScroll: true, onSuccess: () => setUserId("") })}>ربط المستخدم</button>
                    </div>
                </div>
            </div>
        </article>
    );
}

export default function Admin(props: Props) {
    const form = useForm({ name_ar: "", notes: "" });
    const active = props.institutions.filter((i) => i.is_active).length;

    return (
        <AppLayout title="مؤسسات التمويل">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="دليل مؤسسات التمويل الأصغر" description="عرّف المؤسسة، ثم أضف شروط الأهلية واربط مستخدميها. المطابقة تستخدم هذه الشروط ولا تعني موافقة تمويل." />
                <div className="row g-3">
                    <div className="col-6 col-md-4">
                        <StatCard label="المؤسسات" value={String(props.institutions.length)} icon="bi-bank" />
                    </div>
                    <div className="col-6 col-md-4">
                        <StatCard label="فعّالة" value={String(active)} hint="تدخل في المطابقة" icon="bi-check2-circle" tone="blue" />
                    </div>
                </div>
                <SectionCard title="مؤسسة جديدة">
                    <form className="row g-2 align-items-end" onSubmit={(e) => { e.preventDefault(); form.post("/admin/mfis", { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                        <div className="col-12 col-md-5">
                            <TextInput id="name_ar" name="name_ar" label="اسم المؤسسة" value={form.data.name_ar} onChange={(v) => form.setData("name_ar", v)} error={form.errors.name_ar} />
                        </div>
                        <div className="col-12 col-md-5">
                            <TextInput id="notes" name="notes" label="ملاحظات" value={form.data.notes} onChange={(v) => form.setData("notes", v)} />
                        </div>
                        <div className="col-12 col-md-2">
                            <button type="submit" className="btn btn-primary w-100 mb-3" style={{ minHeight: "48px" }} disabled={form.processing}>إضافة</button>
                        </div>
                    </form>
                </SectionCard>
                {props.institutions.length === 0 ? <EmptyState title="لا توجد مؤسسات بعد" description="أضف أول مؤسسة تمويل ثم حدّد شروط الأهلية." /> : null}
                {props.institutions.map((inst) => (
                    <InstitutionCard key={inst.public_id} inst={inst} criterionTypes={props.criterionTypes} complianceRequirements={props.complianceRequirements} mfiUsers={props.mfiUsers} />
                ))}
            </div>
        </AppLayout>
    );
}
