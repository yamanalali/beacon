import { Link, useForm } from "@inertiajs/react";
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { useT } from "../../Hooks/useT";
import { TextInput } from "../../Components/Forms/TextInput";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { ChoiceCards, type ChoiceOption } from "../../Components/Forms/ChoiceCards";
import { FormError } from "../../Components/Forms/FormError";
import "../../../css/enterprise-edit.css";

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
    address_line: string | null;
    registration_status: string | null;
    initial_digital_readiness_note: string | null;
    initial_financial_readiness_note: string | null;
    programme_stage: string | null;
    progress_summary: string | null;
};

type BaselineMeta = {
    has_profile: boolean;
    external_mse_id: string | null;
    imported_at: string | null;
};

type SectionId = "business" | "contact" | "workforce" | "programme" | "readiness";

function text(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return "";
    }
    return String(value);
}

function Section({
    id,
    title,
    description,
    children,
}: {
    id: SectionId;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section id={`edit-${id}`} className="enterprise-edit__section">
            <header className="enterprise-edit__section-head">
                <div>
                    <h2>{title}</h2>
                    <p>{description}</p>
                </div>
            </header>
            <div className="enterprise-edit__section-body">
                <div className="enterprise-edit__grid">{children}</div>
            </div>
        </section>
    );
}

function TextAreaField({
    id,
    label,
    value,
    onChange,
    help,
    error,
    rows = 3,
    mark,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    help?: string;
    error?: string;
    rows?: number;
    mark?: boolean;
}) {
    return (
        <div className={`mb-3 enterprise-edit__span-2${mark ? " enterprise-edit__field-mark" : ""}`}>
            <label htmlFor={id} className="form-label">
                {label}
            </label>
            {help ? <p className="form-text mt-0 mb-2">{help}</p> : null}
            <textarea
                id={id}
                name={id}
                className={`form-control${error ? " is-invalid" : ""}`}
                rows={rows}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <FormError message={error} />
        </div>
    );
}

const defaultRegistrationOptions: ChoiceOption[] = [
    { value: "unregistered", label: "غير مسجّلة", hint: "لا يوجد سجل أو ترخيص ساري حتى الآن" },
    { value: "in_progress", label: "قيد التسجيل", hint: "بدأت إجراءات التسجيل ولم تكتمل" },
    { value: "partial", label: "مسجّلة جزئياً", hint: "بعض الوثائق أو التسجيلات موجودة فقط" },
    { value: "registered", label: "مسجّلة", hint: "التسجيل الأساسي مكتمل قدر الإمكان" },
];

const defaultProgrammeOptions: ChoiceOption[] = [
    { value: "onboarding", label: "التسجيل الأولي", hint: "المنشأة دخلت البرنامج وملء الملف الأساسي" },
    { value: "baseline", label: "تقييم Baseline", hint: "استبيان Baseline قيد الاستكمال أو المراجعة" },
    { value: "baseline_eligible", label: "مؤهلة بعد Baseline", hint: "اجتازت فحوصات أهلية Baseline" },
    { value: "baseline_ineligible", label: "غير مؤهلة بعد Baseline", hint: "لم تستوفِ شروط الأهلية الحالية" },
    { value: "records", label: "بناء السجلات المالية", hint: "التركيز على المعاملات والسجلات المالية" },
    { value: "compliance", label: "الامتثال والوثائق", hint: "استكمال المتطلبات والوثائق النظامية" },
    { value: "readiness", label: "الجاهزية للإحالة", hint: "التحضير لملف الإحالة والتمويل" },
    { value: "referral", label: "الإحالة التمويلية", hint: "إحالة فعّالة أو قيد المتابعة مع جهة تمويل" },
    { value: "active_support", label: "متابعة ودعم", hint: "دعم استشاري مستمر بعد التقدم" },
];

export default function Edit({
    enterprise,
    incomplete_fields = [],
    baseline,
    options,
}: {
    enterprise: EnterpriseProfile;
    incomplete_fields?: string[];
    baseline?: BaselineMeta;
    options?: {
        registration_status?: ChoiceOption[];
        programme_stage?: ChoiceOption[];
    };
}) {
    const t = useT();
    const [activeSection, setActiveSection] = useState<SectionId>("business");

    const locations = [
        { value: "damascus", label: t("smes.location.damascus", undefined, "دمشق") },
        { value: "rural_damascus", label: t("smes.location.rural_damascus", undefined, "ريف دمشق") },
        { value: "daraa", label: t("smes.location.daraa", undefined, "درعا") },
    ];
    const registrationOptions = options?.registration_status?.length
        ? options.registration_status
        : defaultRegistrationOptions;
    const programmeOptions = options?.programme_stage?.length
        ? options.programme_stage
        : defaultProgrammeOptions;

    const form = useForm({
        business_name: enterprise.business_name,
        sector: text(enterprise.sector),
        location: text(enterprise.location),
        founding_date: text(enterprise.founding_date),
        employee_count: text(enterprise.employee_count),
        female_employee_count: text(enterprise.female_employee_count),
        male_employee_count: text(enterprise.male_employee_count),
        owner_name: text(enterprise.owner_name),
        contact_name: text(enterprise.contact_name),
        contact_phone: text(enterprise.contact_phone),
        contact_email: text(enterprise.contact_email),
        address_line: text(enterprise.address_line),
        registration_status: text(enterprise.registration_status),
        initial_digital_readiness_note: text(enterprise.initial_digital_readiness_note),
        initial_financial_readiness_note: text(enterprise.initial_financial_readiness_note),
        programme_stage: text(enterprise.programme_stage),
        progress_summary: text(enterprise.progress_summary),
    });

    const incompleteSet = useMemo(() => new Set(incomplete_fields), [incomplete_fields]);

    const fieldLabels: Record<string, string> = {
        sector: t("smes.sector", undefined, "القطاع"),
        founding_date: t("smes.founding_date", undefined, "تاريخ التأسيس"),
        employee_count: t("smes.employee_count", undefined, "عدد العاملين"),
        owner_name: t("smes.owner_name", undefined, "اسم المالك"),
        registration_status: t("smes.registration_status", undefined, "حالة التسجيل"),
        contact: t("smes.contact_name", undefined, "جهة الاتصال"),
    };

    const trackedKeys = ["sector", "founding_date", "employee_count", "owner_name", "registration_status", "contact"] as const;
    const filledCount = trackedKeys.filter((key) => {
        if (key === "contact") {
            return form.data.contact_name.trim() !== "";
        }
        return text(form.data[key as keyof typeof form.data]).trim() !== "";
    }).length;
    const completeness = Math.round((filledCount / trackedKeys.length) * 100);

    const locationLabel =
        locations.find((item) => item.value === form.data.location)?.label ||
        form.data.location ||
        t("smes.show.not_added", undefined, "غير مضاف");

    const sections: Array<{
        id: SectionId;
        title: string;
        description: string;
        warn: boolean;
        done: boolean;
    }> = [
        {
            id: "business",
            title: t("smes.edit.section.business", undefined, "المنشأة"),
            description: t(
                "smes.edit.section.business_desc",
                undefined,
                "الاسم والموقع والنشاط وعنوان العمل.",
            ),
            warn: incompleteSet.has("sector") || incompleteSet.has("founding_date"),
            done: form.data.business_name.trim() !== "" && form.data.location !== "" && form.data.sector.trim() !== "",
        },
        {
            id: "contact",
            title: t("smes.edit.section.contact", undefined, "التواصل والمالك"),
            description: t(
                "smes.edit.section.contact_desc",
                undefined,
                "من نتواصل معه وكيف نصل للمنشأة.",
            ),
            warn: incompleteSet.has("owner_name") || incompleteSet.has("contact"),
            done: form.data.owner_name.trim() !== "" && form.data.contact_name.trim() !== "" && (form.data.contact_phone.trim() !== "" || form.data.contact_email.trim() !== ""),
        },
        {
            id: "workforce",
            title: t("smes.edit.section.workforce", undefined, "العمالة"),
            description: t(
                "smes.edit.section.workforce_desc",
                undefined,
                "حجم الفريق وتوزيعه إن توفّر.",
            ),
            warn: incompleteSet.has("employee_count"),
            done: form.data.employee_count.trim() !== "",
        },
        {
            id: "programme",
            title: t("smes.edit.section.programme", undefined, "التسجيل والبرنامج"),
            description: t(
                "smes.edit.section.programme_desc",
                undefined,
                "الوضع النظامي ومرحلة المتابعة.",
            ),
            warn: incompleteSet.has("registration_status"),
            done: form.data.registration_status.trim() !== "" && form.data.programme_stage.trim() !== "",
        },
        {
            id: "readiness",
            title: t("smes.edit.section.readiness", undefined, "ملاحظات الجاهزية"),
            description: t(
                "smes.edit.section.readiness_desc",
                undefined,
                "ملاحظات أولية للدعم — ليست درجات تقييم.",
            ),
            warn: false,
            done:
                form.data.initial_digital_readiness_note.trim() !== "" ||
                form.data.initial_financial_readiness_note.trim() !== "" ||
                form.data.progress_summary.trim() !== "",
        },
    ];

    useEffect(() => {
        const keys = Object.keys(form.errors);
        if (keys.length === 0) {
            return;
        }
        const first = document.getElementById(keys[0]);
        first?.scrollIntoView({ behavior: "smooth", block: "center" });
        if (first instanceof HTMLElement) {
            first.focus();
        }
    }, [form.errors]);

    useEffect(() => {
        const nodes = sections.map((section) => document.getElementById(`edit-${section.id}`));
        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                if (!visible?.target?.id) {
                    return;
                }
                const id = visible.target.id.replace("edit-", "") as SectionId;
                setActiveSection(id);
            },
            { rootMargin: "-20% 0px -55% 0px", threshold: [0.2, 0.5, 0.8] },
        );
        nodes.forEach((node) => node && observer.observe(node));
        return () => observer.disconnect();
        // sections is stable enough for mount; ids do not change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(`/enterprises/${enterprise.public_id}`);
    }

    function jumpTo(fieldKey: string) {
        const map: Record<string, { section: SectionId; field: string }> = {
            sector: { section: "business", field: "sector" },
            founding_date: { section: "business", field: "founding_date" },
            employee_count: { section: "workforce", field: "employee_count" },
            owner_name: { section: "contact", field: "owner_name" },
            registration_status: { section: "programme", field: "registration_status" },
            contact: { section: "contact", field: "contact_name" },
        };
        const target = map[fieldKey];
        if (!target) {
            return;
        }
        document.getElementById(`edit-${target.section}`)?.scrollIntoView({ behavior: "smooth", block: "start" });
        window.setTimeout(() => {
            const el = document.getElementById(target.field);
            if (el instanceof HTMLElement) {
                el.focus();
            }
        }, 280);
    }

    const mark = (key: string) => {
        if (key === "contact") {
            return form.data.contact_name.trim() === "";
        }
        const value = text(form.data[key as keyof typeof form.data]);
        return value.trim() === "";
    };

    return (
        <AppLayout title={t("smes.edit_title", undefined, "تعديل الملف")}>
            <div className="app-page enterprise-edit">
                <Link href={`/enterprises/${enterprise.public_id}`} className="enterprise-edit__crumb">
                    <i className="bi bi-arrow-right" aria-hidden="true" />
                    {t("smes.edit.back_to_profile", undefined, "العودة إلى ملف المنشأة")}
                </Link>

                <header className="enterprise-edit__hero">
                    <div className="enterprise-edit__avatar" aria-hidden="true">
                        {(form.data.business_name || enterprise.business_name).trim().charAt(0) || "م"}
                    </div>
                    <div>
                        <h1>{form.data.business_name || enterprise.business_name}</h1>
                        <div className="enterprise-edit__meta">
                            <span className="enterprise-edit__pill">
                                <i className="bi bi-geo-alt" aria-hidden="true" />
                                {locationLabel}
                            </span>
                            {baseline?.external_mse_id ? (
                                <span className="enterprise-edit__pill">
                                    <i className="bi bi-upc" aria-hidden="true" />
                                    <span>{t("smes.show.baseline_mse_id", undefined, "رقم المنشأة")}</span>
                                    <bdi dir="ltr">{baseline.external_mse_id}</bdi>
                                </span>
                            ) : null}
                            <span className={`enterprise-edit__pill${completeness === 100 ? " enterprise-edit__pill--ok" : " enterprise-edit__pill--warn"}`}>
                                <i className={`bi ${completeness === 100 ? "bi-check-circle" : "bi-exclamation-circle"}`} aria-hidden="true" />
                                {completeness === 100
                                    ? t("smes.edit.complete", undefined, "الملف مكتمل")
                                    : t(
                                          "smes.edit.incomplete_count",
                                          { count: String(trackedKeys.length - filledCount) },
                                          `متبقّي ${trackedKeys.length - filledCount} حقول مهمة`,
                                      )}
                            </span>
                        </div>
                    </div>
                </header>

                <form onSubmit={submit} className="enterprise-edit__layout" noValidate>
                    <aside className="enterprise-edit__aside" aria-label={t("smes.edit.aside", undefined, "ملخص التعديل")}>
                        <div className="enterprise-edit__completeness">
                            <h2>{t("smes.edit.completeness", undefined, "اكتمال الملف")}</h2>
                            <p>
                                {t(
                                    "smes.edit.completeness_help",
                                    undefined,
                                    "أكمل الحقول المهمة أولاً حتى يظهر الملف جاهزاً للمتابعة.",
                                )}
                            </p>
                            <div className="enterprise-edit__bar" aria-hidden="true">
                                <span style={{ width: `${completeness}%` }} />
                            </div>
                            <div className="enterprise-edit__score">
                                <strong>{completeness}%</strong>{" "}
                                {t("smes.edit.filled_of", { filled: String(filledCount), total: String(trackedKeys.length) }, `${filledCount} من ${trackedKeys.length}`)}
                            </div>
                            {incomplete_fields.length > 0 ? (
                                <ul className="enterprise-edit__missing">
                                    {incomplete_fields.map((key) => (
                                        <li key={key}>
                                            <button type="button" onClick={() => jumpTo(key)}>
                                                <i className="bi bi-pencil-square me-2" aria-hidden="true" />
                                                {fieldLabels[key] ?? key}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>

                        <nav className="enterprise-edit__nav" aria-label={t("smes.edit.sections", undefined, "أقسام التعديل")}>
                            {sections.map((section) => (
                                <a
                                    key={section.id}
                                    href={`#edit-${section.id}`}
                                    className={activeSection === section.id ? "is-active" : undefined}
                                    onClick={() => setActiveSection(section.id)}
                                >
                                    <span
                                        className={`enterprise-edit__nav-dot${section.warn ? " is-warn" : section.done ? " is-done" : ""}`}
                                        aria-hidden="true"
                                    />
                                    {section.title}
                                </a>
                            ))}
                        </nav>
                    </aside>

                    <div className="enterprise-edit__main">
                        <nav className="enterprise-edit__nav d-lg-none mb-3" aria-label={t("smes.edit.sections", undefined, "أقسام التعديل")}>
                            {sections.map((section) => (
                                <a
                                    key={`m-${section.id}`}
                                    href={`#edit-${section.id}`}
                                    className={activeSection === section.id ? "is-active" : undefined}
                                >
                                    <span
                                        className={`enterprise-edit__nav-dot${section.warn ? " is-warn" : section.done ? " is-done" : ""}`}
                                        aria-hidden="true"
                                    />
                                    {section.title}
                                </a>
                            ))}
                        </nav>

                        <div className="enterprise-edit__sections">
                            <Section id="business" title={sections[0].title} description={sections[0].description}>
                                <div className="enterprise-edit__span-2">
                                    <TextInput
                                        id="business_name"
                                        name="business_name"
                                        label={t("smes.business_name", undefined, "اسم المنشأة")}
                                        value={form.data.business_name}
                                        error={form.errors.business_name}
                                        required
                                        onChange={(value) => form.setData("business_name", value)}
                                    />
                                </div>
                                <div className={mark("sector") ? "enterprise-edit__field-mark" : undefined}>
                                    <TextInput
                                        id="sector"
                                        name="sector"
                                        label={t("smes.sector", undefined, "القطاع")}
                                        value={form.data.sector}
                                        error={form.errors.sector}
                                        help={t("smes.edit.sector_help", undefined, "مثال: تجارة — بيع بالتجزئة")}
                                        onChange={(value) => form.setData("sector", value)}
                                    />
                                </div>
                                <SelectInput
                                    id="location"
                                    label={t("smes.location", undefined, "الموقع")}
                                    value={form.data.location}
                                    options={locations}
                                    error={form.errors.location}
                                    required
                                    onChange={(value) => form.setData("location", value)}
                                />
                                <div className="enterprise-edit__span-2">
                                    <TextInput
                                        id="address_line"
                                        name="address_line"
                                        label={t("smes.address_line", undefined, "العنوان")}
                                        value={form.data.address_line}
                                        error={form.errors.address_line}
                                        help={t("smes.edit.address_help", undefined, "الشارع، الحي، أقرب معلم")}
                                        onChange={(value) => form.setData("address_line", value)}
                                    />
                                </div>
                                <div className={mark("founding_date") ? "enterprise-edit__field-mark" : undefined}>
                                    <TextInput
                                        id="founding_date"
                                        name="founding_date"
                                        type="date"
                                        label={t("smes.founding_date", undefined, "تاريخ التأسيس")}
                                        value={form.data.founding_date}
                                        error={form.errors.founding_date}
                                        onChange={(value) => form.setData("founding_date", value)}
                                    />
                                </div>
                            </Section>

                            <Section id="contact" title={sections[1].title} description={sections[1].description}>
                                <div className={mark("owner_name") ? "enterprise-edit__field-mark" : undefined}>
                                    <TextInput
                                        id="owner_name"
                                        name="owner_name"
                                        label={t("smes.owner_name", undefined, "اسم المالك")}
                                        value={form.data.owner_name}
                                        error={form.errors.owner_name}
                                        onChange={(value) => form.setData("owner_name", value)}
                                    />
                                </div>
                                <div className={mark("contact") ? "enterprise-edit__field-mark" : undefined}>
                                    <TextInput
                                        id="contact_name"
                                        name="contact_name"
                                        label={t("smes.contact_name", undefined, "اسم جهة الاتصال")}
                                        value={form.data.contact_name}
                                        error={form.errors.contact_name}
                                        onChange={(value) => form.setData("contact_name", value)}
                                    />
                                </div>
                                <TextInput
                                    id="contact_phone"
                                    name="contact_phone"
                                    label={t("smes.contact_phone", undefined, "هاتف التواصل")}
                                    value={form.data.contact_phone}
                                    error={form.errors.contact_phone}
                                    help={t("smes.contact_help", undefined, "الهاتف أو البريد كافٍ للتواصل.")}
                                    onChange={(value) => form.setData("contact_phone", value)}
                                />
                                <TextInput
                                    id="contact_email"
                                    name="contact_email"
                                    type="email"
                                    label={t("smes.contact_email", undefined, "البريد الإلكتروني")}
                                    value={form.data.contact_email}
                                    error={form.errors.contact_email}
                                    onChange={(value) => form.setData("contact_email", value)}
                                />
                            </Section>

                            <Section id="workforce" title={sections[2].title} description={sections[2].description}>
                                <p className="enterprise-edit__hint enterprise-edit__span-2">
                                    {t(
                                        "smes.gender_field_help",
                                        undefined,
                                        "حقول الجنس اختيارية للتخزين والمتابعة فقط، وليست إطاراً رسمياً.",
                                    )}
                                </p>
                                <div className={mark("employee_count") ? "enterprise-edit__field-mark" : undefined}>
                                    <TextInput
                                        id="employee_count"
                                        name="employee_count"
                                        type="number"
                                        label={t("smes.employee_count", undefined, "عدد العاملين")}
                                        value={form.data.employee_count}
                                        error={form.errors.employee_count}
                                        onChange={(value) => form.setData("employee_count", value)}
                                    />
                                </div>
                                <TextInput
                                    id="female_employee_count"
                                    name="female_employee_count"
                                    type="number"
                                    label={t("smes.female_employees", undefined, "عدد العاملات")}
                                    value={form.data.female_employee_count}
                                    error={form.errors.female_employee_count}
                                    onChange={(value) => form.setData("female_employee_count", value)}
                                />
                                <TextInput
                                    id="male_employee_count"
                                    name="male_employee_count"
                                    type="number"
                                    label={t("smes.male_employees", undefined, "عدد العاملين الذكور")}
                                    value={form.data.male_employee_count}
                                    error={form.errors.male_employee_count}
                                    onChange={(value) => form.setData("male_employee_count", value)}
                                />
                            </Section>

                            <Section id="programme" title={sections[3].title} description={sections[3].description}>
                                <div
                                    className={`enterprise-edit__span-2${mark("registration_status") ? " enterprise-edit__field-mark" : ""}`}
                                >
                                    <ChoiceCards
                                        id="registration_status"
                                        label={t("smes.registration_status", undefined, "حالة التسجيل")}
                                        value={form.data.registration_status}
                                        options={registrationOptions}
                                        error={form.errors.registration_status}
                                        onChange={(value) => form.setData("registration_status", value)}
                                    />
                                </div>
                                <div className="enterprise-edit__span-2">
                                    <ChoiceCards
                                        id="programme_stage"
                                        label={t("smes.programme_stage", undefined, "مرحلة البرنامج")}
                                        value={form.data.programme_stage}
                                        options={programmeOptions}
                                        error={form.errors.programme_stage}
                                        columns={1}
                                        onChange={(value) => form.setData("programme_stage", value)}
                                    />
                                </div>
                                <TextAreaField
                                    id="progress_summary"
                                    label={t("smes.progress_summary", undefined, "ملخص التقدم")}
                                    value={form.data.progress_summary}
                                    error={form.errors.progress_summary}
                                    onChange={(value) => form.setData("progress_summary", value)}
                                />
                            </Section>

                            <Section id="readiness" title={sections[4].title} description={sections[4].description}>
                                <TextAreaField
                                    id="initial_digital_readiness_note"
                                    label={t("smes.digital_readiness_note", undefined, "ملاحظة الجاهزية الرقمية الأولية")}
                                    help={t("smes.note_not_score", undefined, "هذه ملاحظة وليست درجة")}
                                    value={form.data.initial_digital_readiness_note}
                                    error={form.errors.initial_digital_readiness_note}
                                    onChange={(value) => form.setData("initial_digital_readiness_note", value)}
                                />
                                <TextAreaField
                                    id="initial_financial_readiness_note"
                                    label={t("smes.financial_readiness_note", undefined, "ملاحظة الجاهزية المالية الأولية")}
                                    help={t("smes.note_not_score", undefined, "هذه ملاحظة وليست درجة")}
                                    value={form.data.initial_financial_readiness_note}
                                    error={form.errors.initial_financial_readiness_note}
                                    onChange={(value) => form.setData("initial_financial_readiness_note", value)}
                                />
                            </Section>
                        </div>

                        <div className="enterprise-edit__actions">
                            <span className={`enterprise-edit__dirty${form.isDirty ? " is-on" : ""}`}>
                                {form.isDirty
                                    ? t("smes.edit.unsaved", undefined, "هناك تغييرات غير محفوظة")
                                    : t("smes.edit.saved_state", undefined, "لا تغييرات معلّقة")}
                            </span>
                            <Link href={`/enterprises/${enterprise.public_id}`} className="btn btn-outline-secondary">
                                {t("common.cancel", undefined, "إلغاء")}
                            </Link>
                            <button type="submit" className="btn btn-primary" disabled={form.processing}>
                                {form.processing
                                    ? t("common.saving", undefined, "جارٍ الحفظ...")
                                    : t("smes.save_profile", undefined, "حفظ الملف")}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
