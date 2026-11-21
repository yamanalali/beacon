import { router, useForm, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { TextInput } from "../../../Components/Forms/TextInput";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { useT } from "../../../Hooks/useT";

type FieldRow = {
    id: number;
    key: string;
    label: string;
    is_visible: boolean;
    is_child: boolean;
    is_system: boolean;
};

type SectionRow = {
    key: string;
    label: string;
    is_visible: boolean;
    fields: FieldRow[];
};

type Props = {
    sections: SectionRow[];
    section_labels: { value: string; label: string }[];
};

const INPUT_KINDS: { value: string; label: string }[] = [
    { value: "text", label: "نص" },
    { value: "boolean", label: "نعم / لا" },
    { value: "multi_choice", label: "اختيارات متعددة" },
];

function NewFieldForm({ sectionLabels, onClose }: { sectionLabels: { value: string; label: string }[]; onClose: () => void }) {
    const t = useT();
    const form = useForm({
        key: "",
        label_ar: "",
        section: sectionLabels[0]?.value ?? "other",
        input_kind: "text",
        excel_column: "",
    });

    return (
        <SectionCard title={t("baseline.add_field", undefined, "إضافة حقل")}>
            <form
                style={{ maxWidth: "650px" }}
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post("/admin/baseline-fields", {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            onClose();
                        },
                    });
                }}
            >
                <TextInput
                    id="new_field_key"
                    label={t("baseline.new_field_key", undefined, "مفتاح الحقل (إنجليزي)")}
                    name="key"
                    value={form.data.key}
                    onChange={(value) => form.setData("key", value)}
                    error={form.errors.key}
                    autoComplete="off"
                    required
                />
                <TextInput
                    id="new_field_label"
                    label={t("baseline.new_field_label", undefined, "العنوان بالعربية")}
                    name="label_ar"
                    value={form.data.label_ar}
                    onChange={(value) => form.setData("label_ar", value)}
                    error={form.errors.label_ar}
                    required
                />
                <div className="mb-3">
                    <label htmlFor="new_field_section" className="form-label mb-1 fw-semibold">
                        {t("baseline.new_field_section", undefined, "القسم")}
                    </label>
                    <select
                        id="new_field_section"
                        className="form-select"
                        style={{ minHeight: "48px", fontSize: "16px" }}
                        value={form.data.section}
                        onChange={(event) => form.setData("section", event.target.value)}
                    >
                        {sectionLabels.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    {form.errors.section ? <div className="text-danger small mt-1">{form.errors.section}</div> : null}
                </div>
                <div className="mb-3">
                    <label htmlFor="new_field_kind" className="form-label mb-1 fw-semibold">
                        {t("baseline.new_field_kind", undefined, "نوع الإدخال")}
                    </label>
                    <select
                        id="new_field_kind"
                        className="form-select"
                        style={{ minHeight: "48px", fontSize: "16px" }}
                        value={form.data.input_kind}
                        onChange={(event) => form.setData("input_kind", event.target.value)}
                    >
                        {INPUT_KINDS.map((k) => (
                            <option key={k.value} value={k.value}>
                                {k.label}
                            </option>
                        ))}
                    </select>
                    {form.errors.input_kind ? <div className="text-danger small mt-1">{form.errors.input_kind}</div> : null}
                </div>
                <TextInput
                    id="new_field_excel_column"
                    label={t("baseline.new_field_excel_column", undefined, "عمود Excel (اختياري)")}
                    name="excel_column"
                    value={form.data.excel_column}
                    onChange={(value) => form.setData("excel_column", value.toUpperCase())}
                    error={form.errors.excel_column}
                    autoComplete="off"
                />
                <div className="d-flex flex-wrap gap-2">
                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                        {t("baseline.save_field", undefined, "حفظ الحقل")}
                    </button>
                    <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "48px" }} onClick={onClose}>
                        {t("common.cancel", undefined, "إلغاء")}
                    </button>
                </div>
            </form>
        </SectionCard>
    );
}

export default function Index({ sections, section_labels }: Props) {
    const t = useT();
    const { flash, errors } = usePage().props as { flash?: { success?: string }; errors?: Record<string, string> };
    const [open, setOpen] = useState<string | null>(sections[0]?.key ?? null);
    const [busy, setBusy] = useState(false);
    const [adding, setAdding] = useState(false);

    function hideField(field: FieldRow) {
        setBusy(true);
        router.post(`/admin/baseline-fields/${field.id}/hide`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    function deleteField(field: FieldRow) {
        if (!window.confirm(t("baseline.delete_field_confirm", undefined, "حذف هذا الحقل غير المستخدم؟"))) {
            return;
        }
        setBusy(true);
        router.delete(`/admin/baseline-fields/${field.id}`, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    const visibleCount = useMemo(
        () => sections.reduce((n, s) => n + s.fields.filter((f) => f.is_visible && !f.is_child).length, 0),
        [sections],
    );

    function toggleSection(section: SectionRow, next: boolean) {
        setBusy(true);
        router.put(
            "/admin/baseline-fields/visibility",
            { section: section.key, section_visible: next },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    }

    function toggleField(field: FieldRow, next: boolean) {
        setBusy(true);
        router.put(
            "/admin/baseline-fields/visibility",
            { fields: [{ id: field.id, is_visible: next }] },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    }

    return (
        <AppLayout title={t("admin.baseline.title", undefined, "حقول بروفايل Baseline")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.baseline.title", undefined, "حقول بروفايل Baseline")}
                    description={t(
                        "admin.baseline.description",
                        undefined,
                        "أظهر أو أخفِ أقسام وحقول الاستبيان في بروفايل المنشأة (إعداد عام لكل البرنامج).",
                    )}
                />
                {flash?.success ? (
                    <p className="alert alert-success" role="status">
                        {flash.success}
                    </p>
                ) : null}
                <div className="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <p className="mb-0 text-body-secondary">
                        {t("admin.baseline.visible_count", { count: visibleCount }, `${visibleCount} حقلاً ظاهراً`)}
                    </p>
                    <div className="d-flex flex-wrap gap-2">
                        <button type="button" className="btn btn-primary" style={{ minHeight: 48 }} onClick={() => setAdding(true)} disabled={adding}>
                            {t("baseline.add_field", undefined, "إضافة حقل")}
                        </button>
                        <a href="/admin/baseline/import" className="btn btn-outline-primary" style={{ minHeight: 48 }}>
                            {t("admin.baseline.import_cta", undefined, "استيراد ملف Excel")}
                        </a>
                    </div>
                </div>
                {errors?.field ? (
                    <p className="alert alert-danger mb-0" role="alert">
                        {errors.field}
                    </p>
                ) : null}
                {adding ? <NewFieldForm sectionLabels={section_labels} onClose={() => setAdding(false)} /> : null}
                {sections.map((section) => {
                    const parentFields = section.fields.filter((f) => !f.is_child);
                    const expanded = open === section.key;
                    return (
                        <SectionCard key={section.key} title={section.label}>
                            <div className="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                                <div className="form-check form-switch m-0">
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id={`section-${section.key}`}
                                        checked={section.is_visible}
                                        disabled={busy}
                                        onChange={(e) => toggleSection(section, e.target.checked)}
                                        style={{ width: 48, height: 28 }}
                                    />
                                    <label className="form-check-label fw-semibold ms-2" htmlFor={`section-${section.key}`}>
                                        {t("admin.baseline.show_section", undefined, "إظهار القسم")}
                                    </label>
                                </div>
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary btn-sm"
                                    style={{ minHeight: 44 }}
                                    onClick={() => setOpen(expanded ? null : section.key)}
                                >
                                    {expanded
                                        ? t("common.collapse", undefined, "طي الحقول")
                                        : t("common.expand", undefined, "عرض الحقول")}{" "}
                                    ({parentFields.length})
                                </button>
                            </div>
                            {expanded ? (
                                <ul className="list-group list-group-flush">
                                    {parentFields.map((field) => (
                                        <li
                                            key={field.id}
                                            className="list-group-item d-flex justify-content-between align-items-start gap-3 px-0"
                                        >
                                            <div>
                                                <div className="fw-semibold">{field.label}</div>
                                                <div className="small text-body-secondary">
                                                    <bdi dir="ltr">{field.key}</bdi>
                                                </div>
                                            </div>
                                            <div className="d-flex flex-wrap align-items-center justify-content-end gap-2">
                                                <div className="form-check form-switch m-0">
                                                    <input
                                                        className="form-check-input"
                                                        type="checkbox"
                                                        role="switch"
                                                        id={`field-${field.id}`}
                                                        checked={field.is_visible}
                                                        disabled={busy}
                                                        onChange={(e) => toggleField(field, e.target.checked)}
                                                        style={{ width: 44, height: 24 }}
                                                        aria-label={t("admin.baseline.toggle_field", undefined, "إظهار الحقل")}
                                                    />
                                                </div>
                                                {field.is_visible ? (
                                                    <button type="button" className="btn btn-outline-secondary btn-sm" style={{ minHeight: 44 }} disabled={busy} onClick={() => hideField(field)}>
                                                        {t("baseline.hide_field", undefined, "إخفاء")}
                                                    </button>
                                                ) : null}
                                                {!field.is_system ? (
                                                    <button type="button" className="btn btn-outline-danger btn-sm" style={{ minHeight: 44 }} disabled={busy} onClick={() => deleteField(field)}>
                                                        {t("baseline.delete_field", undefined, "حذف")}
                                                    </button>
                                                ) : null}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </SectionCard>
                    );
                })}
            </div>
        </AppLayout>
    );
}
