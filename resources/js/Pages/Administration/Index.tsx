import { Link } from "@inertiajs/react";
import { useMemo } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatCard } from "../../Components/Common/StatCard";
import { useT } from "../../Hooks/useT";

type Props = {
    stats: {
        users_active: number;
        users_inactive: number;
        enterprises: number;
        audit_last_7_days: number;
    };
    roles: { label: string; count: number }[];
};

export default function Index({ stats, roles }: Props) {
    const t = useT();

    const areas = useMemo(
        () => [
            {
                href: "/users",
                icon: "bi-people",
                title: t("admin.index.area.users", undefined, "المستخدمون والأدوار"),
                text: t("admin.index.area.users_desc", undefined, "إنشاء الحسابات وتفعيلها وإيقافها وتغيير الأدوار."),
            },
            {
                href: "/enterprises",
                icon: "bi-shop",
                title: t("admin.index.area.enterprises", undefined, "سجلات المنشآت"),
                text: t("admin.index.area.enterprises_desc", undefined, "ملفات المنشآت وتعيين المستشارين والمستخدمين."),
            },
            {
                href: "/admin/audit-logs",
                icon: "bi-journal-text",
                title: t("nav.admin.audit-logs", undefined, "سجل التدقيق"),
                text: t("admin.index.area.audit_desc", undefined, "من فعل ماذا ومتى. للقراءة فقط."),
            },
        ],
        [t],
    );

    const settings = useMemo(
        () => [
            { href: "/admin/currencies", label: t("admin.index.setting.currencies", undefined, "العملات والعملة الافتراضية"), icon: "bi-currency-exchange" },
            { href: "/admin/categories", label: t("admin.index.setting.categories", undefined, "تصنيفات العمليات"), icon: "bi-tags" },
            { href: "/admin/invitations", label: t("admin.index.setting.invitations", undefined, "دعوات المستخدمين"), icon: "bi-person-plus" },
            { href: "/admin/compliance", label: t("admin.index.setting.compliance", undefined, "متطلبات الامتثال وإصداراتها"), icon: "bi-patch-check" },
            { href: "/admin/readiness", label: t("admin.index.setting.readiness", undefined, "معايير الجاهزية وإصداراتها"), icon: "bi-sliders" },
            { href: "/admin/mfis", label: t("admin.index.setting.mfis", undefined, "مؤسسات التمويل ومعايير الأهلية"), icon: "bi-bank" },
            { href: "/admin/baseline-fields", label: t("admin.index.setting.baseline", undefined, "حقول بروفايل Baseline وإظهارها"), icon: "bi-clipboard-data" },
            { href: "/admin/baseline/import", label: t("admin.index.setting.baseline_import", undefined, "استيراد استبيان Baseline"), icon: "bi-upload" },
        ],
        [t],
    );

    return (
        <AppLayout title={t("admin.title", undefined, "الإدارة")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.title", undefined, "الإدارة")}
                    description={t("admin.index.description", undefined, "إعدادات البرنامج وإدارة الحسابات والسجلات.")}
                />
                <div className="row g-3">
                    <div className="col-6 col-lg-3">
                        <StatCard label={t("admin.index.stat.active_users", undefined, "حسابات نشطة")} value={String(stats.users_active)} icon="bi-people" />
                    </div>
                    <div className="col-6 col-lg-3">
                        <StatCard
                            label={t("admin.index.stat.inactive_users", undefined, "حسابات موقوفة")}
                            value={String(stats.users_inactive)}
                            icon="bi-person-dash"
                            tone="amber"
                        />
                    </div>
                    <div className="col-6 col-lg-3">
                        <StatCard label={t("smes.title", undefined, "المنشآت")} value={String(stats.enterprises)} icon="bi-buildings" tone="blue" />
                    </div>
                    <div className="col-6 col-lg-3">
                        <StatCard
                            label={t("admin.index.stat.audit_7d", undefined, "عمليات مسجلة (7 أيام)")}
                            value={String(stats.audit_last_7_days)}
                            icon="bi-journal-check"
                            tone="violet"
                        />
                    </div>
                </div>
                <div className="row g-3">
                    {areas.map((area) => (
                        <div key={area.href} className="col-12 col-md-6 col-lg-4">
                            <Link href={area.href} className="card app-module-card h-100 text-decoration-none text-body">
                                <i className={`bi ${area.icon} fs-3 mb-2`} aria-hidden="true" />
                                <span className="fw-semibold">{area.title}</span>
                                <span className="small text-body-secondary">{area.text}</span>
                            </Link>
                        </div>
                    ))}
                </div>
                <div className="row g-3">
                    <div className="col-12 col-lg-6">
                        <SectionCard title={t("admin.index.accounts_by_role", undefined, "الحسابات حسب الدور")}>
                            <ul className="list-unstyled mb-0">
                                {roles.map((role) => (
                                    <li key={role.label} className="d-flex justify-content-between border-bottom py-2">
                                        <span>{role.label}</span>
                                        <span className="fw-semibold">{role.count}</span>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title={t("admin.index.program_settings", undefined, "إعدادات البرنامج")}>
                            <nav className="app-settings-links" aria-label={t("admin.index.program_settings", undefined, "إعدادات البرنامج")}>
                                {settings.map((item) => (
                                    <Link key={item.href} href={item.href}>
                                        <i className={`bi ${item.icon}`} aria-hidden="true" />
                                        <span>{item.label}</span>
                                        <i className="bi bi-chevron-left" aria-hidden="true" />
                                    </Link>
                                ))}
                            </nav>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
