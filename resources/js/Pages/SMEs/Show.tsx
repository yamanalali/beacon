import { Link, useForm } from "@inertiajs/react";
import { useId, useRef, useState, type KeyboardEvent } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { SectionCard } from "../../Components/Common/SectionCard";
import { FormError } from "../../Components/Forms/FormError";
import "../../../css/enterprise-profile.css";

type Person = { id: number; name: string; email?: string };
type Option = { id: number; name: string };
type EnterpriseProfile = {
    public_id: string;
    business_name: string;
    sector: string | null;
    location: string | null;
    founding_date: string | null;
    employee_count: number | null;
    female_employee_count: number | null;
    male_employee_count: number | null;
    owner_name: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    registration_status: string | null;
    initial_digital_readiness_note: string | null;
    initial_financial_readiness_note: string | null;
    programme_stage: string | null;
    progress_summary: string | null;
};
type Props = {
    enterprise: EnterpriseProfile;
    incomplete_fields: string[];
    members: Person[] | null;
    assignments: Person[] | null;
    advisor_options: Option[];
    member_options: Option[];
    can_assign: boolean;
};
type Tab = "overview" | "details" | "readiness" | "team";
const locationLabels: Record<string, string> = {
    damascus: "دمشق",
    rural_damascus: "ريف دمشق",
    daraa: "درعا",
};
const incompleteLabels: Record<string, string> = {
    sector: "القطاع",
    founding_date: "تاريخ التأسيس",
    employee_count: "عدد العاملين",
    owner_name: "اسم المالك",
    contact: "اسم جهة الاتصال",
    registration_status: "حالة التسجيل",
};

function Field({
    label,
    value,
    ltr = false,
    wide = false,
}: {
    label: string;
    value: string | number | null;
    ltr?: boolean;
    wide?: boolean;
}) {
    const missing = value === null || value === "";
    return (
        <div
            className={`enterprise-profile__field${wide ? " enterprise-profile__field--wide" : ""}`}
        >
            <dt>{label}</dt>
            <dd>
                {missing ? (
                    <span className="enterprise-profile__missing">
                        غير مضاف
                    </span>
                ) : (
                    <bdi dir={ltr ? "ltr" : "auto"}>{value}</bdi>
                )}
            </dd>
        </div>
    );
}

function People({ people, empty }: { people: Person[]; empty: string }) {
    if (!people.length)
        return (
            <p className="enterprise-profile__quiet">
                <i className="bi bi-person-plus" aria-hidden="true" />
                {empty}
            </p>
        );
    return (
        <ul
            className="enterprise-profile__people"
            tabIndex={people.length > 5 ? 0 : undefined}
            aria-label="قائمة الأشخاص المرتبطين بالمنشأة"
        >
            {people.map((person) => (
                <li key={person.id}>
                    <span
                        className="enterprise-profile__person-avatar"
                        aria-hidden="true"
                    >
                        {person.name.trim().charAt(0)}
                    </span>
                    <span>
                        <strong>{person.name}</strong>
                        {person.email ? (
                            <bdi dir="ltr">{person.email}</bdi>
                        ) : null}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default function Show({
    enterprise,
    incomplete_fields,
    members,
    assignments,
    advisor_options,
    member_options,
    can_assign,
}: Props) {
    const advisorForm = useForm({ user_id: "" });
    const memberForm = useForm({ user_id: "" });
    const [selectedTab, setSelectedTab] = useState<Tab>("overview");
    const tabList = useRef<HTMLDivElement>(null);
    const prefix = useId();
    const base = `/enterprises/${enterprise.public_id}`;
    const location = enterprise.location
        ? (locationLabels[enterprise.location] ?? enterprise.location)
        : null;
    const hasTeam = members !== null || assignments !== null || can_assign;
    const tabs: { key: Tab; label: string; icon: string }[] = [
        { key: "overview", label: "نظرة عامة", icon: "bi-grid-1x2" },
        { key: "details", label: "بيانات المنشأة", icon: "bi-building" },
        {
            key: "readiness",
            label: "الجاهزية والمتابعة",
            icon: "bi-shield-check",
        },
        ...(hasTeam
            ? [
                  {
                      key: "team" as const,
                      label: "فريق المنشأة",
                      icon: "bi-people",
                  },
              ]
            : []),
    ];
    const activeTab = tabs.some((tab) => tab.key === selectedTab)
        ? selectedTab
        : "overview";
    const modules = [
        {
            path: "transactions",
            label: "المعاملات المالية",
            text: "المبيعات والمشتريات والدفعات",
            icon: "bi-wallet2",
        },
        {
            path: "documents",
            label: "المستندات",
            text: "الفواتير والوثائق والإيصالات",
            icon: "bi-folder2-open",
        },
        {
            path: "reports",
            label: "التقارير المالية",
            text: "قراءة الوضع المالي للمنشأة",
            icon: "bi-bar-chart-line",
        },
        {
            path: "compliance",
            label: "الامتثال",
            text: "المتطلبات والمواعيد",
            icon: "bi-patch-check",
        },
        {
            path: "readiness",
            label: "تقييم الجاهزية",
            text: "التقييمات ومعايير الاستعداد",
            icon: "bi-shield-check",
        },
        {
            path: "referrals",
            label: "الإحالات",
            text: "متابعة إحالات التمويل",
            icon: "bi-send",
        },
    ];

    function openTab(tab: Tab) {
        setSelectedTab(tab);
        document.getElementById(`${prefix}-tab-${tab}`)?.focus();
    }

    function navigateTabs(
        event: KeyboardEvent<HTMLButtonElement>,
        index: number,
    ) {
        const rtl = document.documentElement.dir === "rtl";
        let next: number;
        if (event.key === "Home") next = 0;
        else if (event.key === "End") next = tabs.length - 1;
        else if (event.key === "ArrowRight")
            next = (index + (rtl ? -1 : 1) + tabs.length) % tabs.length;
        else if (event.key === "ArrowLeft")
            next = (index + (rtl ? 1 : -1) + tabs.length) % tabs.length;
        else return;
        event.preventDefault();
        setSelectedTab(tabs[next].key);
        const button =
            tabList.current?.querySelectorAll<HTMLButtonElement>(
                '[role="tab"]',
            )[next];
        button?.focus();
        button?.scrollIntoView({ block: "nearest", inline: "nearest" });
    }

    return (
        <AppLayout title={enterprise.business_name}>
            <div className="app-page enterprise-profile">
                <nav
                    className="enterprise-profile__breadcrumb"
                    aria-label="مسار ملف المنشأة"
                >
                    <Link href="/enterprises">المنشآت</Link>
                    <i className="bi bi-chevron-left" aria-hidden="true" />
                    <span>ملف المنشأة</span>
                </nav>
                <header className="enterprise-profile__hero">
                    <div className="enterprise-profile__identity">
                        <span
                            className="enterprise-profile__logo"
                            aria-hidden="true"
                        >
                            <i className="bi bi-buildings" />
                        </span>
                        <div>
                            <span className="enterprise-profile__eyebrow">
                                ملف المنشأة
                            </span>
                            <h1>{enterprise.business_name}</h1>
                            <div className="enterprise-profile__meta">
                                {enterprise.sector ? (
                                    <span>
                                        <i
                                            className="bi bi-briefcase"
                                            aria-hidden="true"
                                        />
                                        {enterprise.sector}
                                    </span>
                                ) : null}
                                {location ? (
                                    <span>
                                        <i
                                            className="bi bi-geo-alt"
                                            aria-hidden="true"
                                        />
                                        {location}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </div>
                    <Link
                        href={`${base}/edit`}
                        className="btn btn-primary enterprise-profile__edit"
                    >
                        <i className="bi bi-pencil-square" aria-hidden="true" />
                        تعديل الملف
                    </Link>
                </header>

                {incomplete_fields.length ? (
                    <aside
                        className="enterprise-profile__notice"
                        aria-label="معلومات تحتاج استكمال"
                    >
                        <i className="bi bi-info-circle" aria-hidden="true" />
                        <div>
                            <strong>
                                باقي بعض المعلومات لاستكمال ملف المنشأة
                            </strong>
                            <p>
                                {incomplete_fields
                                    .map(
                                        (field) =>
                                            incompleteLabels[field] ?? field,
                                    )
                                    .join("، ")}
                            </p>
                        </div>
                        <Link href={`${base}/edit`}>
                            استكمال البيانات
                            <i
                                className="bi bi-arrow-left"
                                aria-hidden="true"
                            />
                        </Link>
                    </aside>
                ) : null}

                <div
                    ref={tabList}
                    className="enterprise-profile__tabs"
                    role="tablist"
                    aria-label="أقسام ملف المنشأة"
                >
                    {tabs.map((tab, index) => (
                        <button
                            key={tab.key}
                            id={`${prefix}-tab-${tab.key}`}
                            type="button"
                            role="tab"
                            aria-selected={activeTab === tab.key}
                            aria-controls={`${prefix}-panel-${tab.key}`}
                            tabIndex={activeTab === tab.key ? 0 : -1}
                            onClick={() => setSelectedTab(tab.key)}
                            onKeyDown={(event) => navigateTabs(event, index)}
                        >
                            <i
                                className={`bi ${tab.icon}`}
                                aria-hidden="true"
                            />
                            {tab.label}
                        </button>
                    ))}
                </div>

                <section
                    id={`${prefix}-panel-overview`}
                    role="tabpanel"
                    aria-labelledby={`${prefix}-tab-overview`}
                    hidden={activeTab !== "overview"}
                    className="enterprise-profile__panel"
                    tabIndex={0}
                >
                    <div className="row g-4">
                        <div className="col-12 col-xl-8 d-flex flex-column gap-4">
                            <SectionCard title="المنشأة في لمحة">
                                <dl className="enterprise-profile__fields">
                                    <Field
                                        label="اسم المالك"
                                        value={enterprise.owner_name}
                                    />
                                    <Field
                                        label="تاريخ التأسيس"
                                        value={enterprise.founding_date}
                                        ltr
                                    />
                                    <Field
                                        label="عدد العاملين"
                                        value={enterprise.employee_count}
                                    />
                                    <Field
                                        label="حالة التسجيل"
                                        value={enterprise.registration_status}
                                    />
                                </dl>
                                <button
                                    type="button"
                                    className="enterprise-profile__text-action"
                                    onClick={() => openTab("details")}
                                >
                                    عرض جميع بيانات المنشأة
                                    <i
                                        className="bi bi-arrow-left"
                                        aria-hidden="true"
                                    />
                                </button>
                            </SectionCard>
                            <SectionCard title="أعمال المنشأة">
                                <nav
                                    className="enterprise-profile__modules"
                                    aria-label="خدمات المنشأة"
                                >
                                    {modules.map((module) => (
                                        <Link
                                            key={module.path}
                                            href={`${base}/${module.path}`}
                                        >
                                            <span
                                                className={`enterprise-profile__module-icon enterprise-profile__module-icon--${module.path}`}
                                            >
                                                <i
                                                    className={`bi ${module.icon}`}
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span>
                                                <strong>{module.label}</strong>
                                                <small>{module.text}</small>
                                            </span>
                                            <i
                                                className="bi bi-chevron-left"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    ))}
                                </nav>
                            </SectionCard>
                        </div>
                        <aside className="col-12 col-xl-4 d-flex flex-column gap-4">
                            <section
                                className="enterprise-profile__programme"
                                aria-labelledby={`${prefix}-programme`}
                            >
                                <span className="enterprise-profile__programme-icon">
                                    <i
                                        className="bi bi-signpost-split"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p>رحلة المنشأة مع بيكون</p>
                                <h2 id={`${prefix}-programme`}>
                                    {enterprise.programme_stage ||
                                        "لم تُحدّد مرحلة البرنامج بعد"}
                                </h2>
                                <p className="enterprise-profile__summary">
                                    {enterprise.progress_summary ||
                                        "سيظهر هنا ملخص التقدّم عند إضافته إلى الملف."}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => openTab("details")}
                                >
                                    عرض تفاصيل البرنامج
                                    <i
                                        className="bi bi-arrow-left"
                                        aria-hidden="true"
                                    />
                                </button>
                            </section>
                            <SectionCard title="جهة الاتصال">
                                <dl className="enterprise-profile__fields enterprise-profile__fields--single">
                                    <Field
                                        label="الاسم"
                                        value={enterprise.contact_name}
                                    />
                                    <Field
                                        label="الهاتف"
                                        value={enterprise.contact_phone}
                                        ltr
                                    />
                                    <Field
                                        label="البريد الإلكتروني"
                                        value={enterprise.contact_email}
                                        ltr
                                    />
                                </dl>
                            </SectionCard>
                        </aside>
                    </div>
                </section>

                <section
                    id={`${prefix}-panel-details`}
                    role="tabpanel"
                    aria-labelledby={`${prefix}-tab-details`}
                    hidden={activeTab !== "details"}
                    className="enterprise-profile__panel"
                    tabIndex={0}
                >
                    <div className="enterprise-profile__section-intro">
                        <h2>بيانات المنشأة</h2>
                        <p>
                            معلومات التعريف والتواصل والتسجيل، مرتّبة حسب
                            الموضوع.
                        </p>
                    </div>
                    <div className="row g-4">
                        <div className="col-12 col-xl-6">
                            <SectionCard title="التعريف بالمنشأة">
                                <dl className="enterprise-profile__fields">
                                    <Field
                                        label="اسم المنشأة"
                                        value={enterprise.business_name}
                                        wide
                                    />
                                    <Field
                                        label="القطاع"
                                        value={enterprise.sector}
                                    />
                                    <Field label="الموقع" value={location} />
                                    <Field
                                        label="تاريخ التأسيس"
                                        value={enterprise.founding_date}
                                        ltr
                                    />
                                    <Field
                                        label="اسم المالك"
                                        value={enterprise.owner_name}
                                    />
                                </dl>
                            </SectionCard>
                        </div>
                        <div className="col-12 col-xl-6">
                            <SectionCard title="التواصل">
                                <dl className="enterprise-profile__fields">
                                    <Field
                                        label="اسم جهة الاتصال"
                                        value={enterprise.contact_name}
                                        wide
                                    />
                                    <Field
                                        label="الهاتف"
                                        value={enterprise.contact_phone}
                                        ltr
                                    />
                                    <Field
                                        label="البريد الإلكتروني"
                                        value={enterprise.contact_email}
                                        ltr
                                    />
                                </dl>
                            </SectionCard>
                        </div>
                        <div className="col-12 col-xl-6">
                            <SectionCard title="العاملون في المنشأة">
                                <dl className="enterprise-profile__fields">
                                    <Field
                                        label="إجمالي العاملين"
                                        value={enterprise.employee_count}
                                    />
                                    <Field
                                        label="عدد العاملات"
                                        value={enterprise.female_employee_count}
                                    />
                                    <Field
                                        label="عدد العاملين الذكور"
                                        value={enterprise.male_employee_count}
                                    />
                                </dl>
                                <p className="enterprise-profile__footnote">
                                    أعداد العاملات والعاملين اختيارية وليست
                                    إطاراً رسمياً للنوع الاجتماعي.
                                </p>
                            </SectionCard>
                        </div>
                        <div className="col-12 col-xl-6">
                            <SectionCard title="التسجيل والبرنامج">
                                <dl className="enterprise-profile__fields">
                                    <Field
                                        label="حالة التسجيل"
                                        value={enterprise.registration_status}
                                    />
                                    <Field
                                        label="مرحلة البرنامج"
                                        value={enterprise.programme_stage}
                                    />
                                    <Field
                                        label="ملخص التقدم"
                                        value={enterprise.progress_summary}
                                        wide
                                    />
                                </dl>
                            </SectionCard>
                        </div>
                    </div>
                </section>

                <section
                    id={`${prefix}-panel-readiness`}
                    role="tabpanel"
                    aria-labelledby={`${prefix}-tab-readiness`}
                    hidden={activeTab !== "readiness"}
                    className="enterprise-profile__panel"
                    tabIndex={0}
                >
                    <div className="enterprise-profile__section-intro">
                        <h2>الجاهزية والمتابعة</h2>
                        <p>
                            الملاحظات الأولية والإرشاد وحالة الموافقة في مكان
                            واحد.
                        </p>
                    </div>
                    <div className="row g-4">
                        <div className="col-12 col-xl-7 d-flex flex-column gap-4">
                            <SectionCard title="ملاحظات الجاهزية الأولية">
                                <p className="enterprise-profile__inline-note">
                                    <i
                                        className="bi bi-info-circle"
                                        aria-hidden="true"
                                    />
                                    هذه ملاحظات أولية وليست درجات تقييم.
                                </p>
                                <dl className="enterprise-profile__fields enterprise-profile__fields--single">
                                    <Field
                                        label="الجاهزية الرقمية الأولية"
                                        value={
                                            enterprise.initial_digital_readiness_note
                                        }
                                    />
                                    <Field
                                        label="الجاهزية المالية الأولية"
                                        value={
                                            enterprise.initial_financial_readiness_note
                                        }
                                    />
                                </dl>
                            </SectionCard>
                            <SectionCard title="الموافقة">
                                <div className="enterprise-profile__pending">
                                    <i
                                        className="bi bi-file-earmark-check"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <strong>
                                            بانتظار اعتماد نص الموافقة
                                        </strong>
                                        <p>
                                            سجل الموافقة غير متاح حتى يعتمد
                                            بيكون نص الموافقة.
                                        </p>
                                    </div>
                                </div>
                            </SectionCard>
                        </div>
                        <div className="col-12 col-xl-5">
                            <SectionCard title="الإرشاد والمتابعة">
                                <div className="enterprise-profile__advisory">
                                    <span>
                                        <i
                                            className="bi bi-chat-square-text"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <h3>متابعة الخطوة التالية</h3>
                                    <p>
                                        انتقل إلى سجل الإرشاد لمراجعة النشاطات
                                        وإجراءات المتابعة.
                                    </p>
                                    <Link
                                        href={`${base}/advisory`}
                                        className="btn btn-primary"
                                    >
                                        عرض الإرشاد والمتابعة
                                        <i
                                            className="bi bi-arrow-left"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </div>
                            </SectionCard>
                        </div>
                    </div>
                </section>

                {hasTeam ? (
                    <section
                        id={`${prefix}-panel-team`}
                        role="tabpanel"
                        aria-labelledby={`${prefix}-tab-team`}
                        hidden={activeTab !== "team"}
                        className="enterprise-profile__panel"
                        tabIndex={0}
                    >
                        <div className="enterprise-profile__section-intro">
                            <h2>فريق المنشأة</h2>
                            <p>
                                المستخدمون المرتبطون بالمنشأة والمستشارون
                                المعيّنون لها.
                            </p>
                        </div>
                        <div className="row g-4">
                            {members !== null || can_assign ? (
                                <div className="col-12 col-xl-6">
                                    <SectionCard title="مستخدمو المنشأة">
                                        {members !== null ? (
                                            <People
                                                people={members}
                                                empty="لا يوجد مستخدم مرتبط بالمنشأة بعد."
                                            />
                                        ) : null}
                                        {can_assign ? (
                                            <form
                                                className="enterprise-profile__assign-form"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    memberForm.post(
                                                        `${base}/members`,
                                                    );
                                                }}
                                            >
                                                <label
                                                    htmlFor="member_user_id"
                                                    className="form-label"
                                                >
                                                    ربط مستخدم منشأة
                                                </label>
                                                <select
                                                    id="member_user_id"
                                                    className={`form-select${memberForm.errors.user_id ? " is-invalid" : ""}`}
                                                    aria-invalid={
                                                        memberForm.errors
                                                            .user_id
                                                            ? true
                                                            : undefined
                                                    }
                                                    value={
                                                        memberForm.data.user_id
                                                    }
                                                    onChange={(event) =>
                                                        memberForm.setData(
                                                            "user_id",
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        اختر مستخدماً
                                                    </option>
                                                    {member_options.map(
                                                        (option) => (
                                                            <option
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <FormError
                                                    message={
                                                        memberForm.errors
                                                            .user_id
                                                    }
                                                />
                                                <button
                                                    type="submit"
                                                    className="btn btn-primary mt-3"
                                                    disabled={
                                                        memberForm.processing
                                                    }
                                                >
                                                    ربط المستخدم
                                                </button>
                                            </form>
                                        ) : null}
                                    </SectionCard>
                                </div>
                            ) : null}
                            {assignments !== null || can_assign ? (
                                <div className="col-12 col-xl-6">
                                    <SectionCard title="المستشارون">
                                        {assignments !== null ? (
                                            <People
                                                people={assignments}
                                                empty="لا يوجد مستشار معيّن للمنشأة بعد."
                                            />
                                        ) : null}
                                        {can_assign ? (
                                            <form
                                                className="enterprise-profile__assign-form"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    advisorForm.post(
                                                        `${base}/advisors`,
                                                    );
                                                }}
                                            >
                                                <label
                                                    htmlFor="advisor_user_id"
                                                    className="form-label"
                                                >
                                                    تعيين مستشار
                                                </label>
                                                <select
                                                    id="advisor_user_id"
                                                    className={`form-select${advisorForm.errors.user_id ? " is-invalid" : ""}`}
                                                    aria-invalid={
                                                        advisorForm.errors
                                                            .user_id
                                                            ? true
                                                            : undefined
                                                    }
                                                    value={
                                                        advisorForm.data.user_id
                                                    }
                                                    onChange={(event) =>
                                                        advisorForm.setData(
                                                            "user_id",
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        اختر مستشاراً
                                                    </option>
                                                    {advisor_options.map(
                                                        (option) => (
                                                            <option
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <FormError
                                                    message={
                                                        advisorForm.errors
                                                            .user_id
                                                    }
                                                />
                                                <button
                                                    type="submit"
                                                    className="btn btn-primary mt-3"
                                                    disabled={
                                                        advisorForm.processing
                                                    }
                                                >
                                                    تعيين المستشار
                                                </button>
                                            </form>
                                        ) : null}
                                    </SectionCard>
                                </div>
                            ) : null}
                        </div>
                    </section>
                ) : null}
            </div>
        </AppLayout>
    );
}
