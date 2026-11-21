import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type Category = {
    id: number;
    key: string;
    label_ar: string;
    label_en: string | null;
    type_scope: string | null;
    is_active: boolean;
    sort: number;
    used: boolean;
};
type TypeOption = { value: string; label: string };

function TypeSelect({ id, value, types, onChange }: { id: string; value: string; types: TypeOption[]; onChange: (v: string) => void }) {
    const t = useT();
    return (
        <select id={id} className="form-select" value={value} onChange={(e) => onChange(e.target.value)}>
            <option value="">{t("admin.categories.all_types", undefined, "كل الأنواع")}</option>
            {types.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    );
}

function Row({ category: c, types }: { category: Category; types: TypeOption[] }) {
    const t = useT();
    const form = useForm({
        label_ar: c.label_ar,
        label_en: c.label_en ?? "",
        type_scope: c.type_scope ?? "",
        is_active: c.is_active,
        sort: String(c.sort),
    });

    return (
        <form
            className="app-mobile-record"
            data-testid="category-row"
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/admin/categories/${c.id}`, { preserveScroll: true });
            }}
        >
            <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <strong>
                    <bdi dir="ltr">{c.key}</bdi>
                </strong>
                {c.is_active ? null : <StatusBadge label={t("admin.categories.inactive", undefined, "موقوف")} tone="neutral" />}
            </div>
            <div className="row g-3">
                <div className="col-12 col-md-6">
                    <label className="form-label" htmlFor={`label-ar-${c.id}`}>
                        {t("admin.categories.label_ar", undefined, "الاسم بالعربية")}
                    </label>
                    <input
                        id={`label-ar-${c.id}`}
                        className="form-control"
                        required
                        value={form.data.label_ar}
                        onChange={(e) => form.setData("label_ar", e.target.value)}
                    />
                </div>
                <div className="col-12 col-md-6">
                    <label className="form-label" htmlFor={`label-en-${c.id}`}>
                        {t("admin.categories.label_en", undefined, "الاسم بالإنكليزية (اختياري)")}
                    </label>
                    <input
                        id={`label-en-${c.id}`}
                        className="form-control"
                        dir="ltr"
                        value={form.data.label_en}
                        onChange={(e) => form.setData("label_en", e.target.value)}
                    />
                </div>
                <div className="col-12 col-md-6">
                    <label className="form-label" htmlFor={`scope-${c.id}`}>
                        {t("admin.categories.type_scope", undefined, "نوع العملية")}
                    </label>
                    <TypeSelect id={`scope-${c.id}`} value={form.data.type_scope} types={types} onChange={(v) => form.setData("type_scope", v)} />
                </div>
                <div className="col-12 col-md-6">
                    <label className="form-label" htmlFor={`sort-${c.id}`}>
                        {t("admin.categories.sort", undefined, "الترتيب")}
                    </label>
                    <input
                        id={`sort-${c.id}`}
                        className="form-control"
                        type="number"
                        min={0}
                        value={form.data.sort}
                        onChange={(e) => form.setData("sort", e.target.value)}
                    />
                </div>
            </div>
            <div className="d-flex flex-wrap gap-3 align-items-center mt-3">
                <label>
                    <input
                        type="checkbox"
                        className="form-check-input me-2"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData("is_active", e.target.checked)}
                    />
                    {t("admin.categories.active", undefined, "متاح للعمليات الجديدة")}
                </label>
                <div className="ms-auto d-flex flex-wrap gap-2">
                    {c.is_active ? (
                        <button
                            type="button"
                            className="btn btn-outline-danger"
                            style={{ minHeight: 44 }}
                            disabled={form.processing}
                            onClick={() => {
                                if (window.confirm(t("admin.categories.deactivate_confirm", undefined, "هل تريد إيقاف هذا التصنيف؟ لن يظهر في العمليات الجديدة وتبقى السجلات السابقة كما هي."))) {
                                    router.post(`/admin/categories/${c.id}/deactivate`, {}, { preserveScroll: true });
                                }
                            }}
                        >
                            {t("admin.categories.deactivate", undefined, "إيقاف التصنيف")}
                        </button>
                    ) : null}
                    <button type="submit" className="btn btn-primary" style={{ minHeight: 44 }} disabled={form.processing}>
                        {t("common.save", undefined, "حفظ")}
                    </button>
                </div>
            </div>
            {Object.values(form.errors).map((error, i) => (
                <p className="text-danger mt-2 mb-0" role="alert" key={i}>
                    {error}
                </p>
            ))}
        </form>
    );
}

export default function Categories({ categories, types }: { categories: Category[]; types: TypeOption[] }) {
    const t = useT();
    const form = useForm({ key: "", label_ar: "", label_en: "", type_scope: "", is_active: true, sort: "0" });

    return (
        <AppLayout title={t("admin.categories.title", undefined, "التصنيفات")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.categories.page_title", undefined, "إدارة تصنيفات العمليات")}
                    description={t(
                        "admin.categories.description",
                        undefined,
                        "التصنيفات التي تظهر عند تسجيل العمليات. إيقاف التصنيف يمنع استخدامه في عمليات جديدة، وتبقى السجلات السابقة كما هي.",
                    )}
                />
                <SectionCard title={t("admin.categories.available", undefined, "التصنيفات الحالية")}>
                    {categories.length === 0 ? (
                        <EmptyState
                            title={t(
                                "admin.categories.empty",
                                undefined,
                                "لا توجد تصنيفات محفوظة بعد. تُستخدم التصنيفات الافتراضية حتى تضيف تصنيفاً.",
                            )}
                        />
                    ) : (
                        <div className="d-flex flex-column gap-3">
                            {categories.map((c) => (
                                <Row key={`${c.id}-${c.label_ar}-${c.label_en}-${c.type_scope}-${c.is_active}-${c.sort}`} category={c} types={types} />
                            ))}
                        </div>
                    )}
                </SectionCard>
                <SectionCard title={t("admin.categories.add", undefined, "إضافة تصنيف")}>
                    <form
                        className="row g-3 align-items-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/admin/categories", { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                    >
                        <div className="col-12 col-md-4">
                            <label htmlFor="category-key" className="form-label">
                                {t("admin.categories.key_label", undefined, "الرمز (إنكليزي، لا يتغير لاحقاً)")}
                            </label>
                            <input
                                id="category-key"
                                className="form-control"
                                dir="ltr"
                                required
                                maxLength={60}
                                value={form.data.key}
                                onChange={(e) => form.setData("key", e.target.value.toLowerCase())}
                            />
                            {form.errors.key ? (
                                <p className="text-danger small mt-1 mb-0" role="alert">
                                    {form.errors.key}
                                </p>
                            ) : null}
                        </div>
                        <div className="col-12 col-md-4">
                            <label htmlFor="category-label-ar" className="form-label">
                                {t("admin.categories.label_ar", undefined, "الاسم بالعربية")}
                            </label>
                            <input
                                id="category-label-ar"
                                className="form-control"
                                required
                                value={form.data.label_ar}
                                onChange={(e) => form.setData("label_ar", e.target.value)}
                            />
                            {form.errors.label_ar ? (
                                <p className="text-danger small mt-1 mb-0" role="alert">
                                    {form.errors.label_ar}
                                </p>
                            ) : null}
                        </div>
                        <div className="col-12 col-md-4">
                            <label htmlFor="category-label-en" className="form-label">
                                {t("admin.categories.label_en", undefined, "الاسم بالإنكليزية (اختياري)")}
                            </label>
                            <input
                                id="category-label-en"
                                className="form-control"
                                dir="ltr"
                                value={form.data.label_en}
                                onChange={(e) => form.setData("label_en", e.target.value)}
                            />
                        </div>
                        <div className="col-12 col-md-4">
                            <label htmlFor="category-scope" className="form-label">
                                {t("admin.categories.type_scope", undefined, "نوع العملية")}
                            </label>
                            <TypeSelect id="category-scope" value={form.data.type_scope} types={types} onChange={(v) => form.setData("type_scope", v)} />
                            {form.errors.type_scope ? (
                                <p className="text-danger small mt-1 mb-0" role="alert">
                                    {form.errors.type_scope}
                                </p>
                            ) : null}
                        </div>
                        <div className="col-12 col-md-4">
                            <label htmlFor="category-sort" className="form-label">
                                {t("admin.categories.sort", undefined, "الترتيب")}
                            </label>
                            <input
                                id="category-sort"
                                className="form-control"
                                type="number"
                                min={0}
                                value={form.data.sort}
                                onChange={(e) => form.setData("sort", e.target.value)}
                            />
                        </div>
                        <div className="col-12 col-md-4">
                            <button className="btn btn-primary w-100" style={{ minHeight: 48 }} disabled={form.processing}>
                                {t("admin.categories.add", undefined, "إضافة تصنيف")}
                            </button>
                        </div>
                    </form>
                </SectionCard>
            </div>
        </AppLayout>
    );
}
