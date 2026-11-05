import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatCard } from "../../Components/Common/StatCard";
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

    const active = requirements.filter((r) => r.is_active).length;
    const drafts = requirements.reduce((n, r) => n + r.versions.filter((v) => v.status === "draft").length, 0);

    return (
        <AppLayout title="متطلبات الامتثال">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title="إدارة متطلبات الامتثال"
                    description="أضف المتطلب كمسودة، ثم انشر النسخة عندما يصبح الإرشاد جاهزاً. التقييمات السابقة تبقى على النسخة التي استُخدمت وقتها."
                />

                <div className="row g-3">
                    <div className="col-12 col-md-4">
                        <StatCard label="المتطلبات" value={String(requirements.length)} hint="كل المتطلبات المسجلة" icon="bi-list-check" />
                    </div>
                    <div className="col-6 col-md-4">
                        <StatCard label="فعّالة" value={String(active)} hint="تظهر للمنشآت" icon="bi-check2-circle" tone="blue" />
                    </div>
                    <div className="col-6 col-md-4">
                        <StatCard label="مسودات" value={String(drafts)} hint="بانتظار النشر" icon="bi-pencil" tone="amber" />
                    </div>
                </div>

                <div className="row g-3 align-items-start">
                    <div className="col-12 col-lg-4">
                        <SectionCard title="متطلب جديد">
                            <p className="small text-body-secondary">يُحفظ كمسودة. لا يظهر للمنشأة قبل نشر النسخة.</p>
                            <form
                                className="d-flex flex-column gap-1"
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
                                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>حفظ كمسودة</button>
                            </form>
                        </SectionCard>
                    </div>

                    <div className="col-12 col-lg-8 d-flex flex-column gap-3">
                        {requirements.length === 0 ? <EmptyState title="لا توجد متطلبات بعد" description="أضف أول متطلب من النموذج، ثم انشر النسخة عندما يعتمد بيكون الإرشاد." /> : null}
                        {requirements.map((req) => (
                            <article key={req.public_id} className="border rounded p-3 bg-white">
                                <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <div className="small text-body-secondary">{req.category_label}</div>
                                        <h2 className="h5 mb-0"><bdi dir="ltr">{req.code}</bdi></h2>
                                    </div>
                                    <StatusBadge label={req.is_active ? "فعّال" : req.replaced_by ? `مستبدل بـ ${req.replaced_by}` : "غير فعّال"} tone={req.is_active ? "success" : "warning"} />
                                </div>
                                <ul className="list-unstyled d-flex flex-column gap-2 mt-3 mb-0">
                                    {req.versions.map((v) => (
                                        <li key={v.id} className="border rounded p-3">
                                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                                <span className="fw-semibold">نسخة {v.version_number}: {v.title_ar}</span>
                                                <StatusBadge label={v.status === "published" ? "منشورة" : "مسودة"} tone={v.status === "published" ? "success" : "warning"} />
                                            </div>
                                            <div className="d-flex flex-wrap gap-2 mt-2">
                                                {v.requires_document ? <StatusBadge label="يتطلب وثيقة" tone="neutral" /> : null}
                                                {v.effective_from ? <span className="small text-body-secondary">السريان <bdi dir="ltr">{v.effective_from}</bdi></span> : null}
                                            </div>
                                            {v.guidance_pending ? <p className="small text-body-secondary mb-0 mt-2">الإرشاد بانتظار مدخلات بيكون</p> : null}
                                            {v.published_at ? <p className="small mb-0 mt-2">نشرها {v.published_by} في <bdi dir="ltr">{v.published_at}</bdi></p> : null}
                                            {v.change_summary ? <p className="small mb-0 mt-1">التغيير: {v.change_summary}</p> : null}
                                            {v.status === "draft" ? (
                                                <button type="button" className="btn btn-primary btn-sm mt-3" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/compliance/versions/${v.id}/publish`, {}, { preserveScroll: true })}>
                                                    نشر النسخة
                                                </button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                                {req.is_active ? (
                                    <button type="button" className="btn btn-outline-danger btn-sm mt-3" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/compliance/${req.public_id}/deactivate`, {}, { preserveScroll: true })}>
                                        إيقاف المتطلب
                                    </button>
                                ) : null}
                            </article>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
