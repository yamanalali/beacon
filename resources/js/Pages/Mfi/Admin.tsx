import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type Criterion = { id: number; label_ar: string; type_label: string; parameters: Record<string, number> | null; is_active: boolean };
type Institution = { public_id: string; name_ar: string; notes: string | null; is_active: boolean; users: { id: number; name: string }[]; criteria: Criterion[] };
type Props = { institutions: Institution[]; criterionTypes: Record<string, string>; complianceRequirements: { id: number; code: string }[]; mfiUsers: { id: number; name: string; email: string }[] };

function InstitutionCard({ inst, criterionTypes, complianceRequirements, mfiUsers }: { inst: Institution } & Omit<Props, "institutions">) {
    const form = useForm({ label_ar: "", type: "manual_review", min_score: "", compliance_requirement_id: "" });
    const [userId, setUserId] = useState("");

    return (
        <article className="border rounded p-3 bg-white">
            <div className="d-flex justify-content-between flex-wrap gap-2">
                <strong>{inst.name_ar}</strong>
                <StatusBadge label={inst.is_active ? "فعّالة" : "غير فعّالة"} tone={inst.is_active ? "success" : "warning"} />
            </div>
            <h3 className="h6 mt-3">شروط الأهلية</h3>
            <ul>
                {inst.criteria.map((c) => (
                    <li key={c.id}>
                        {c.label_ar} - {c.type_label}{c.parameters?.min_score !== undefined ? ` (${c.parameters.min_score})` : ""}{c.is_active ? "" : " - موقوف"}
                        {c.is_active ? <button type="button" className="btn btn-link btn-sm" onClick={() => router.post(`/admin/mfis/criteria/${c.id}/deactivate`, {}, { preserveScroll: true })}>إيقاف</button> : null}
                    </li>
                ))}
            </ul>
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/admin/mfis/${inst.public_id}/criteria`, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                <TextInput id={`label-${inst.public_id}`} name="label_ar" label="وصف الشرط" value={form.data.label_ar} onChange={(v) => form.setData("label_ar", v)} error={form.errors.label_ar} />
                <select aria-label="نوع الشرط" className="form-select mb-2" style={{ minHeight: "48px" }} value={form.data.type} onChange={(e) => form.setData("type", e.target.value)}>
                    {Object.entries(criterionTypes).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                </select>
                {form.data.type === "min_readiness_score" ? (
                    <TextInput id={`min-${inst.public_id}`} name="min_score" type="number" label="الحد الأدنى" value={form.data.min_score} onChange={(v) => form.setData("min_score", v)} error={form.errors.min_score} />
                ) : null}
                {form.data.type === "compliance_requirement_completed" ? (
                    <select aria-label="المتطلب" className="form-select mb-2" style={{ minHeight: "48px" }} value={form.data.compliance_requirement_id} onChange={(e) => form.setData("compliance_requirement_id", e.target.value)}>
                        <option value="">اختر المتطلب</option>
                        {complianceRequirements.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}
                    </select>
                ) : null}
                <button type="submit" className="btn btn-outline-primary" style={{ minHeight: "44px" }}>إضافة شرط</button>
            </form>
            <h3 className="h6 mt-3">مستخدمو المؤسسة</h3>
            <p className="mb-2">{inst.users.map((u) => u.name).join("، ") || "لا يوجد"}</p>
            <div className="d-flex gap-2 flex-wrap">
                <select aria-label="مستخدم" className="form-select w-auto" style={{ minHeight: "44px" }} value={userId} onChange={(e) => setUserId(e.target.value)}>
                    <option value="">اختر مستخدم مؤسسة تمويل</option>
                    {mfiUsers.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                </select>
                <button type="button" className="btn btn-outline-primary" style={{ minHeight: "44px" }} disabled={!userId} onClick={() => router.post(`/admin/mfis/${inst.public_id}/users`, { user_id: userId }, { preserveScroll: true })}>ربط</button>
            </div>
        </article>
    );
}

export default function Admin(props: Props) {
    const form = useForm({ name_ar: "", notes: "" });

    return (
        <AppLayout title="مؤسسات التمويل">
            <div className="app-page">
                <PageHeader title="دليل مؤسسات التمويل الأصغر" description="الحقول وشروط الأهلية تُدخل حسب ما تعتمده بيكون." />
                <SectionCard title="مؤسسة جديدة">
                    <form onSubmit={(e) => { e.preventDefault(); form.post("/admin/mfis", { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                        <TextInput id="name_ar" name="name_ar" label="اسم المؤسسة" value={form.data.name_ar} onChange={(v) => form.setData("name_ar", v)} error={form.errors.name_ar} />
                        <TextInput id="notes" name="notes" label="ملاحظات" value={form.data.notes} onChange={(v) => form.setData("notes", v)} />
                        <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }}>إضافة</button>
                    </form>
                </SectionCard>
                <div className="mt-4 d-flex flex-column gap-3">
                    {props.institutions.length === 0 ? <EmptyState title="لا توجد مؤسسات بعد" /> : null}
                    {props.institutions.map((inst) => (
                        <InstitutionCard key={inst.public_id} inst={inst} criterionTypes={props.criterionTypes} complianceRequirements={props.complianceRequirements} mfiUsers={props.mfiUsers} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
