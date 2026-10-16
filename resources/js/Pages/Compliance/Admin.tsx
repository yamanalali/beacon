import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type Version = {
    id: number;
    version_number: number;
    title_ar: string;
    guidance_pending: boolean;
    requires_document: boolean;
    effective_from: string | null;
    status: "draft" | "published";
    change_summary: string | null;
    published_by: string | null;
    published_at: string | null;
};

type Requirement = {
    public_id: string;
    code: string;
    category_label: string;
    is_active: boolean;
    replaced_by: string | null;
    versions: Version[];
};

type Props = { requirements: Requirement[]; categories: Record<string, string> };

export default function Admin({ requirements, categories }: Props) {
    const form = useForm({
        code: "",
        category: Object.keys(categories)[0] ?? "",
        title_ar: "",
        description_ar: "",
        guidance_ar: "",
        requires_document: false,
        effective_from: "",
    });

    return (
        <AppLayout title="متطلبات الامتثال">
            <div className="app-page">
                <PageHeader
                    title="إدارة متطلبات الامتثال"
                    description="كل تغيير يُنشر كنسخة جديدة، والتقييمات القديمة تحتفظ بالنسخة التي استُخدمت وقتها."
                />

                <SectionCard title="متطلب جديد">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/admin/compliance", { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                    >
                        <TextInput id="code" name="code" label="الرمز" value={form.data.code} onChange={(v) => form.setData("code", v)} error={form.errors.code} />
                        <div className="mb-3">
                            <label htmlFor="category" className="form-label mb-1 fw-semibold">التصنيف</label>
                            <select id="category" className="form-select" style={{ minHeight: "48px" }} value={form.data.category} onChange={(e) => form.setData("category", e.target.value)}>
                                {Object.entries(categories).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                        </div>
                        <TextInput id="title_ar" name="title_ar" label="العنوان" value={form.data.title_ar} onChange={(v) => form.setData("title_ar", v)} error={form.errors.title_ar} />
                        <TextInput id="description_ar" name="description_ar" label="الوصف" value={form.data.description_ar} onChange={(v) => form.setData("description_ar", v)} />
                        <TextInput
                            id="guidance_ar"
                            name="guidance_ar"
                            label="الإرشاد المعتمد"
                            value={form.data.guidance_ar}
                            onChange={(v) => form.setData("guidance_ar", v)}
                            help="اتركه فارغاً إذا كان المحتوى بانتظار اعتماد بيكون."
                        />
                        <TextInput id="effective_from" name="effective_from" type="date" label="تاريخ السريان" value={form.data.effective_from} onChange={(v) => form.setData("effective_from", v)} />
                        <div className="form-check mb-3">
                            <input id="requires_document" type="checkbox" className="form-check-input" checked={form.data.requires_document} onChange={(e) => form.setData("requires_document", e.target.checked)} />
                            <label htmlFor="requires_document" className="form-check-label">يتطلب وثيقة</label>
                        </div>
                        <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>حفظ كمسودة</button>
                    </form>
                </SectionCard>

                <div className="mt-4 d-flex flex-column gap-3">
                    {requirements.length === 0 ? <EmptyState title="لا توجد متطلبات بعد. المحتوى بانتظار مدخلات بيكون." /> : null}
                    {requirements.map((req) => (
                        <article key={req.public_id} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                <strong>{req.code} - {req.category_label}</strong>
                                <StatusBadge label={req.is_active ? "فعّال" : req.replaced_by ? `مستبدل بـ ${req.replaced_by}` : "غير فعّال"} tone={req.is_active ? "success" : "warning"} />
                            </div>
                            <ul className="list-unstyled mb-2">
                                {req.versions.map((v) => (
                                    <li key={v.id} className="border-top pt-2 mt-2">
                                        <div className="d-flex justify-content-between flex-wrap gap-2">
                                            <span>نسخة {v.version_number}: {v.title_ar}</span>
                                            <StatusBadge label={v.status === "published" ? "منشورة" : "مسودة"} tone={v.status === "published" ? "success" : "warning"} />
                                        </div>
                                        {v.guidance_pending ? <small className="text-muted d-block">الإرشاد بانتظار مدخلات بيكون</small> : null}
                                        {v.published_at ? <small className="d-block">نشرها {v.published_by} في {v.published_at}</small> : null}
                                        {v.change_summary ? <small className="d-block">التغيير: {v.change_summary}</small> : null}
                                        {v.status === "draft" ? (
                                            <button type="button" className="btn btn-outline-primary btn-sm mt-2" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/compliance/versions/${v.id}/publish`, {}, { preserveScroll: true })}>
                                                نشر النسخة
                                            </button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                            {req.is_active ? (
                                <button type="button" className="btn btn-outline-danger btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/compliance/${req.public_id}/deactivate`, {}, { preserveScroll: true })}>
                                    إيقاف المتطلب
                                </button>
                            ) : null}
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
