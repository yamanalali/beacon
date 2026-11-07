import { Link, router, useForm } from "@inertiajs/react";
import { useMemo } from "react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { useT } from "../../../Hooks/useT";

type Permissions = { view: boolean; record: boolean; approve: boolean; export: boolean; require_approval: boolean };

export default function Access({
    target,
    canEdit,
}: {
    target: { id: number; name: string; role: string; permissions: Permissions; custom: boolean };
    canEdit: boolean;
}) {
    const t = useT();
    const form = useForm({ permissions: target.permissions });

    const fields = useMemo(
        (): [keyof Permissions, string, string][] => [
            [
                "view",
                t("admin.users.access.view", undefined, "عرض بيانات المنشآت"),
                t("admin.users.access.view_help", undefined, "ضمن المنشآت المرتبطة بالحساب ودوره فقط."),
            ],
            [
                "record",
                t("admin.users.access.record", undefined, "تسجيل معاملات مالية"),
                t("admin.users.access.record_help", undefined, "لا يمنح الوصول إلى منشآت غير مسندة."),
            ],
            [
                "approve",
                t("admin.users.access.approve", undefined, "اعتماد المعاملات"),
                t("admin.users.access.approve_help", undefined, "يسمح باعتماد معاملات الآخرين المنتظرة فقط."),
            ],
            [
                "export",
                t("admin.users.access.export", undefined, "تصدير وتنزيل المعلومات"),
                t(
                    "admin.users.access.export_help",
                    undefined,
                    "إتاحة تصدير التقارير والقواعد وسجل التدقيق وتنزيل المستندات ضمن صلاحيات الدور.",
                ),
            ],
            [
                "require_approval",
                t("admin.users.access.require_approval", undefined, "معاملاته الجديدة تحتاج موافقة"),
                t(
                    "admin.users.access.require_approval_help",
                    undefined,
                    "لا تدخل التقارير قبل اعتمادها من مستخدم آخر. تُجرى التصحيحات والدفعات اللاحقة بواسطة مشرف مخول.",
                ),
            ],
        ],
        [t],
    );

    return (
        <AppLayout title={t("admin.users.access.title", undefined, "صلاحيات المستخدم")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.users.access.for_user", { name: target.name }, `صلاحيات ${target.name}`)}
                    description={`${target.role} · ${
                        target.custom
                            ? t("admin.users.access.custom", undefined, "صلاحيات مخصصة")
                            : t("admin.users.access.role_defaults", undefined, "إعدادات الدور الافتراضية")
                    }`}
                />
                <Link href="/users" className="btn btn-light align-self-start">
                    {t("nav.admin.users", undefined, "المستخدمون")}
                </Link>
                <div className="alert alert-info">
                    {t(
                        "admin.users.access.info",
                        undefined,
                        "هذه الصلاحيات تُضاف إلى حدود الدور وتعيينات المنشآت؛ لا تتجاوز عزل بيانات المنشآت أو حدود مستخدمي التمويل والإدارة التقنية. يمكن تعيين المنشآت من صفحة المنشآت.",
                    )}
                </div>
                <SectionCard title={t("admin.users.access.section", undefined, "الصلاحيات والمسؤوليات")}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(`/users/${target.id}/access`, { preserveScroll: true });
                        }}
                    >
                        <div className="d-flex flex-column gap-3">
                            {fields.map(([key, label, help]) => (
                                <label key={key} className="app-mobile-record d-flex gap-3">
                                    <input
                                        type="checkbox"
                                        className="form-check-input flex-shrink-0"
                                        checked={form.data.permissions[key]}
                                        disabled={!canEdit || form.processing}
                                        onChange={(e) =>
                                            form.setData("permissions", { ...form.data.permissions, [key]: e.target.checked })
                                        }
                                    />
                                    <span>
                                        <strong className="d-block">{label}</strong>
                                        <span className="small text-body-secondary">{help}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {Object.values(form.errors).map((error, i) => (
                            <p className="text-danger" role="alert" key={i}>
                                {error}
                            </p>
                        ))}
                        {canEdit ? (
                            <div className="d-flex flex-wrap gap-2 mt-3">
                                <button className="btn btn-primary" disabled={form.processing}>
                                    {t("admin.users.access.save", undefined, "حفظ الصلاحيات")}
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary"
                                    disabled={form.processing}
                                    onClick={() => router.put(`/users/${target.id}/access`, { permissions: null })}
                                >
                                    {t("admin.users.access.reset_role", undefined, "استعادة صلاحيات الدور")}
                                </button>
                            </div>
                        ) : (
                            <p className="text-body-secondary mt-3 mb-0">
                                {t("admin.users.access.cannot_edit_self", undefined, "لا يمكنك تعديل صلاحيات حسابك من هذه الصفحة.")}
                            </p>
                        )}
                    </form>
                </SectionCard>
            </div>
        </AppLayout>
    );
}
