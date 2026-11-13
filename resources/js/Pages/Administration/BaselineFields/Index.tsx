import { router, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
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
};

export default function Index({ sections }: Props) {
    const t = useT();
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [open, setOpen] = useState<string | null>(sections[0]?.key ?? null);
    const [busy, setBusy] = useState(false);

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
                    <a href="/admin/baseline/import" className="btn btn-outline-primary" style={{ minHeight: 48 }}>
                        {t("admin.baseline.import_cta", undefined, "استيراد ملف Excel")}
                    </a>
                </div>
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
