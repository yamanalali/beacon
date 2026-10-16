import { Link } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatCard } from "../../Components/Common/StatCard";

type Props = {
    stats: {
        users_active: number;
        users_inactive: number;
        enterprises: number;
        audit_last_7_days: number;
    };
    roles: { label: string; count: number }[];
};

const areas = [
    {
        href: "/users",
        icon: "bi-people",
        title: "المستخدمون والأدوار",
        text: "إنشاء الحسابات وتفعيلها وإيقافها وتغيير الأدوار.",
    },
    {
        href: "/enterprises",
        icon: "bi-shop",
        title: "سجلات المنشآت",
        text: "ملفات المنشآت وتعيين المستشارين والمستخدمين.",
    },
    {
        href: "/admin/audit-logs",
        icon: "bi-journal-text",
        title: "سجل التدقيق",
        text: "من فعل ماذا ومتى. للقراءة فقط.",
    },
];

const settings = [
    {
        href: "/admin/compliance",
        label: "متطلبات الامتثال وإصداراتها",
        icon: "bi-patch-check",
    },
    {
        href: "/admin/readiness",
        label: "معايير الجاهزية وإصداراتها",
        icon: "bi-sliders",
    },
    {
        href: "/admin/mfis",
        label: "مؤسسات التمويل ومعايير الأهلية",
        icon: "bi-bank",
    },
];

export default function Index({ stats, roles }: Props) {
    return (
        <AppLayout title="الإدارة">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title="الإدارة"
                    description="إعدادات البرنامج وإدارة الحسابات والسجلات."
                />
                <div className="row g-3">
                    <div className="col-6 col-lg-3">
                        <StatCard
                            label="حسابات نشطة"
                            value={String(stats.users_active)}
                            icon="bi-people"
                        />
                    </div>
                    <div className="col-6 col-lg-3">
                        <StatCard
                            label="حسابات موقوفة"
                            value={String(stats.users_inactive)}
                            icon="bi-person-dash"
                            tone="amber"
                        />
                    </div>
                    <div className="col-6 col-lg-3">
                        <StatCard
                            label="المنشآت"
                            value={String(stats.enterprises)}
                            icon="bi-buildings"
                            tone="blue"
                        />
                    </div>
                    <div className="col-6 col-lg-3">
                        <StatCard
                            label="عمليات مسجلة (7 أيام)"
                            value={String(stats.audit_last_7_days)}
                            icon="bi-journal-check"
                            tone="violet"
                        />
                    </div>
                </div>
                <div className="row g-3">
                    {areas.map((area) => (
                        <div
                            key={area.href}
                            className="col-12 col-md-6 col-lg-4"
                        >
                            <Link
                                href={area.href}
                                className="card app-module-card h-100 text-decoration-none text-body"
                            >
                                <i
                                    className={`bi ${area.icon} fs-3 mb-2`}
                                    aria-hidden="true"
                                />
                                <span className="fw-semibold">
                                    {area.title}
                                </span>
                                <span className="small text-body-secondary">
                                    {area.text}
                                </span>
                            </Link>
                        </div>
                    ))}
                </div>
                <div className="row g-3">
                    <div className="col-12 col-lg-6">
                        <SectionCard title="الحسابات حسب الدور">
                            <ul className="list-unstyled mb-0">
                                {roles.map((role) => (
                                    <li
                                        key={role.label}
                                        className="d-flex justify-content-between border-bottom py-2"
                                    >
                                        <span>{role.label}</span>
                                        <span className="fw-semibold">
                                            {role.count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title="إعدادات البرنامج">
                            <nav
                                className="app-settings-links"
                                aria-label="إعدادات البرنامج"
                            >
                                {settings.map((item) => (
                                    <Link key={item.href} href={item.href}>
                                        <i
                                            className={`bi ${item.icon}`}
                                            aria-hidden="true"
                                        />
                                        <span>{item.label}</span>
                                        <i
                                            className="bi bi-chevron-left"
                                            aria-hidden="true"
                                        />
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
