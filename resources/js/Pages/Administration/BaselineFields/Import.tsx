import { Link, useForm, usePage } from "@inertiajs/react";
import { FormEvent } from "react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { FormError } from "../../../Components/Forms/FormError";
import { useT } from "../../../Hooks/useT";

export default function Import() {
    const t = useT();
    const { flash } = usePage().props as { flash?: { success?: string } };
    const form = useForm<{ file: File | null }>({ file: null });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/admin/baseline/import", { forceFormData: true });
    }

    return (
        <AppLayout title={t("admin.baseline.import_title", undefined, "استيراد Baseline")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.baseline.import_title", undefined, "استيراد Baseline")}
                    description={t(
                        "admin.baseline.import_description",
                        undefined,
                        "ارفع تصدير KoBo (xlsx) لإنشاء أو تحديث بروفايلات المنشآت من الاستبيان.",
                    )}
                />
                {flash?.success ? (
                    <p className="alert alert-success" role="status">
                        {flash.success}
                    </p>
                ) : null}
                <SectionCard title={t("admin.baseline.import_file", undefined, "ملف Excel")}>
                    <form onSubmit={submit} style={{ maxWidth: "40rem" }} noValidate>
                        <label htmlFor="baseline-file" className="form-label fw-semibold">
                            {t("admin.baseline.choose_file", undefined, "اختر ملف .xlsx")}
                        </label>
                        <input
                            id="baseline-file"
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            className={`form-control${form.errors.file ? " is-invalid" : ""}`}
                            style={{ minHeight: 48 }}
                            onChange={(e) => form.setData("file", e.target.files?.[0] ?? null)}
                        />
                        <FormError message={form.errors.file} />
                        <div className="d-flex gap-2 mt-3">
                            <button type="submit" className="btn btn-primary" style={{ minHeight: 48 }} disabled={form.processing || !form.data.file}>
                                {t("admin.baseline.start_import", undefined, "بدء الاستيراد")}
                            </button>
                            <Link href="/admin/baseline-fields" className="btn btn-outline-secondary" style={{ minHeight: 48 }}>
                                {t("admin.baseline.back_fields", undefined, "إعدادات الحقول")}
                            </Link>
                        </div>
                    </form>
                </SectionCard>
            </div>
        </AppLayout>
    );
}
