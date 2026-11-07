import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { useT } from "../../Hooks/useT";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatCard } from "../../Components/Common/StatCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type Criterion = {
    id: number;
    label_ar: string;
    type_label: string;
    parameters: Record<string, number> | null;
    is_active: boolean;
};

type Institution = {
    public_id: string;
    name_ar: string;
    code: string | null;
    name_en: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    region_ar: string | null;
    address_ar: string | null;
    website: string | null;
    focus_ar: string | null;
    loan_size_min: string | number | null;
    loan_size_max: string | number | null;
    loan_currency: string | null;
    notes: string | null;
    is_active: boolean;
    users: { id: number; name: string; email: string }[];
    criteria: Criterion[];
};

type Props = {
    institutions: Institution[];
    criterionTypes: Record<string, string>;
    complianceRequirements: { id: number; code: string }[];
    mfiUsers: { id: number; name: string; email: string }[];
    regionSuggestions: string[];
};

type ProfileFields = {
    name_ar: string;
    code: string;
    name_en: string;
    contact_name: string;
    contact_phone: string;
    contact_email: string;
    region_ar: string;
    address_ar: string;
    website: string;
    focus_ar: string;
    loan_size_min: string;
    loan_size_max: string;
    loan_currency: string;
    notes: string;
};

const emptyProfile = (): ProfileFields => ({
    name_ar: "",
    code: "",
    name_en: "",
    contact_name: "",
    contact_phone: "",
    contact_email: "",
    region_ar: "",
    address_ar: "",
    website: "",
    focus_ar: "",
    loan_size_min: "",
    loan_size_max: "",
    loan_currency: "SYP",
    notes: "",
});

function fromInstitution(inst: Institution): ProfileFields {
    return {
        name_ar: inst.name_ar ?? "",
        code: inst.code ?? "",
        name_en: inst.name_en ?? "",
        contact_name: inst.contact_name ?? "",
        contact_phone: inst.contact_phone ?? "",
        contact_email: inst.contact_email ?? "",
        region_ar: inst.region_ar ?? "",
        address_ar: inst.address_ar ?? "",
        website: inst.website ?? "",
        focus_ar: inst.focus_ar ?? "",
        loan_size_min: inst.loan_size_min != null ? String(inst.loan_size_min) : "",
        loan_size_max: inst.loan_size_max != null ? String(inst.loan_size_max) : "",
        loan_currency: inst.loan_currency ?? "SYP",
        notes: inst.notes ?? "",
    };
}

function formatLoanRange(inst: Institution): string | null {
    if (inst.loan_size_min == null && inst.loan_size_max == null) {
        return null;
    }
    const currency = inst.loan_currency ?? "SYP";
    const min = inst.loan_size_min != null ? Number(inst.loan_size_min).toLocaleString("ar") : "—";
    const max = inst.loan_size_max != null ? Number(inst.loan_size_max).toLocaleString("ar") : "—";
    return `${min} – ${max} ${currency}`;
}

function ProfileFormFields({
    idPrefix,
    data,
    errors,
    setData,
    regionSuggestions,
}: {
    idPrefix: string;
    data: ProfileFields;
    errors: Partial<Record<keyof ProfileFields, string>>;
    setData: <K extends keyof ProfileFields>(key: K, value: ProfileFields[K]) => void;
    regionSuggestions: string[];
}) {
    const t = useT();
    return (
        <div className="row g-2">
            <div className="col-12 col-md-6">
                <TextInput id={`${idPrefix}-name_ar`} name="name_ar" label={t("mfi.admin.name_ar", undefined, "اسم المؤسسة بالعربية")} value={data.name_ar} onChange={(v) => setData("name_ar", v)} error={errors.name_ar} required />
            </div>
            <div className="col-12 col-md-3">
                <TextInput id={`${idPrefix}-code`} name="code" label={t("mfi.admin.internal_code", undefined, "الرمز الداخلي")} value={data.code} onChange={(v) => setData("code", v)} error={errors.code} help={t("mfi.admin.code_help", undefined, "اختياري، للتمييز في التقارير")} placeholder="MFI-DMS" />
            </div>
            <div className="col-12 col-md-3">
                <TextInput id={`${idPrefix}-name_en`} name="name_en" label={t("mfi.admin.name_en", undefined, "الاسم بالإنجليزية")} value={data.name_en} onChange={(v) => setData("name_en", v)} error={errors.name_en} />
            </div>

            <div className="col-12"><hr className="my-2" /></div>
            <div className="col-12"><p className="fw-semibold mb-1">{t("mfi.admin.contact_section", undefined, "جهة التواصل")}</p></div>
            <div className="col-12 col-md-4">
                <TextInput id={`${idPrefix}-contact_name`} name="contact_name" label={t("mfi.admin.contact_name", undefined, "اسم المسؤول")} value={data.contact_name} onChange={(v) => setData("contact_name", v)} error={errors.contact_name} />
            </div>
            <div className="col-12 col-md-4">
                <TextInput id={`${idPrefix}-contact_phone`} name="contact_phone" label={t("mfi.admin.phone", undefined, "الهاتف")} value={data.contact_phone} onChange={(v) => setData("contact_phone", v)} error={errors.contact_phone} />
            </div>
            <div className="col-12 col-md-4">
                <TextInput id={`${idPrefix}-contact_email`} name="contact_email" type="email" label={t("smes.contact_email", undefined, "البريد الإلكتروني")} value={data.contact_email} onChange={(v) => setData("contact_email", v)} error={errors.contact_email} />
            </div>

            <div className="col-12"><hr className="my-2" /></div>
            <div className="col-12"><p className="fw-semibold mb-1">{t("mfi.admin.coverage_section", undefined, "التغطية والموقع")}</p></div>
            <div className="col-12 col-md-4">
                <TextInput id={`${idPrefix}-region_ar`} name="region_ar" label={t("mfi.admin.coverage_region", undefined, "منطقة التغطية")} value={data.region_ar} onChange={(v) => setData("region_ar", v)} error={errors.region_ar} list={`${idPrefix}-regions`} help={t("mfi.admin.region_help", undefined, "مثال: دمشق، ريف دمشق، درعا")} />
                <datalist id={`${idPrefix}-regions`}>
                    {regionSuggestions.map((r) => (
                        <option key={r} value={r} />
                    ))}
                </datalist>
            </div>
            <div className="col-12 col-md-8">
                <TextInput id={`${idPrefix}-address_ar`} name="address_ar" label={t("mfi.admin.address", undefined, "العنوان")} value={data.address_ar} onChange={(v) => setData("address_ar", v)} error={errors.address_ar} />
            </div>
            <div className="col-12 col-md-6">
                <TextInput id={`${idPrefix}-website`} name="website" type="url" label={t("mfi.admin.website", undefined, "الموقع الإلكتروني")} value={data.website} onChange={(v) => setData("website", v)} error={errors.website} placeholder="https://" />
            </div>

            <div className="col-12"><hr className="my-2" /></div>
            <div className="col-12"><p className="fw-semibold mb-1">{t("mfi.admin.financing_section", undefined, "التمويل المعروض (معلومات إرشادية)")}</p></div>
            <div className="col-12">
                <div className="mb-3">
                    <label className="form-label" htmlFor={`${idPrefix}-focus_ar`}>{t("mfi.admin.focus", undefined, "مجال التركيز / المنتجات")}</label>
                    <textarea
                        id={`${idPrefix}-focus_ar`}
                        name="focus_ar"
                        className={`form-control${errors.focus_ar ? " is-invalid" : ""}`}
                        rows={3}
                        value={data.focus_ar}
                        onChange={(e) => setData("focus_ar", e.target.value)}
                        placeholder={t("mfi.admin.focus_placeholder", undefined, "مثال: رأس مال عامل للمنشآت الصغيرة، تمويل نسائي، تمويل زراعي…")}
                    />
                    {errors.focus_ar ? <div className="invalid-feedback d-block">{errors.focus_ar}</div> : null}
                    <div className="form-text">{t("mfi.admin.focus_help", undefined, "وصف حر للمنتجات — شروط الأهلية تُضبط بشكل منفصل أدناه.")}</div>
                </div>
            </div>
            <div className="col-6 col-md-3">
                <TextInput id={`${idPrefix}-loan_min`} name="loan_size_min" type="number" label={t("mfi.admin.loan_min", undefined, "حد أدنى للقرض")} value={data.loan_size_min} onChange={(v) => setData("loan_size_min", v)} error={errors.loan_size_min} />
            </div>
            <div className="col-6 col-md-3">
                <TextInput id={`${idPrefix}-loan_max`} name="loan_size_max" type="number" label={t("mfi.admin.loan_max", undefined, "حد أعلى للقرض")} value={data.loan_size_max} onChange={(v) => setData("loan_size_max", v)} error={errors.loan_size_max} />
            </div>
            <div className="col-6 col-md-3">
                <TextInput id={`${idPrefix}-loan_currency`} name="loan_currency" label={t("mfi.admin.loan_currency", undefined, "عملة القرض")} value={data.loan_currency} onChange={(v) => setData("loan_currency", v)} error={errors.loan_currency} placeholder="SYP" />
            </div>
            <div className="col-12">
                <div className="mb-3">
                    <label className="form-label" htmlFor={`${idPrefix}-notes`}>{t("mfi.admin.internal_notes", undefined, "ملاحظات داخلية")}</label>
                    <textarea
                        id={`${idPrefix}-notes`}
                        name="notes"
                        className={`form-control${errors.notes ? " is-invalid" : ""}`}
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData("notes", e.target.value)}
                    />
                    {errors.notes ? <div className="invalid-feedback d-block">{errors.notes}</div> : null}
                </div>
            </div>
        </div>
    );
}

function InstitutionCard({
    inst,
    criterionTypes,
    complianceRequirements,
    mfiUsers,
    regionSuggestions,
}: { inst: Institution } & Omit<Props, "institutions">) {
    const t = useT();
    const criterionForm = useForm({ label_ar: "", type: "manual_review", min_score: "", compliance_requirement_id: "" });
    const profileForm = useForm({ ...fromInstitution(inst), is_active: inst.is_active });
    const [userId, setUserId] = useState("");
    const [editing, setEditing] = useState(false);
    const activeCriteria = inst.criteria.filter((c) => c.is_active).length;
    const loanRange = formatLoanRange(inst);

    return (
        <article className="border rounded p-3 bg-white">
            <div className="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h2 className="h5 mb-1">
                        {inst.name_ar}
                        {inst.code ? <span className="text-body-secondary fw-normal small ms-2">({inst.code})</span> : null}
                    </h2>
                    {inst.name_en ? <p className="small text-body-secondary mb-1" lang="en">{inst.name_en}</p> : null}
                    <ul className="list-unstyled small text-body-secondary mb-0 d-flex flex-column gap-1">
                        {inst.region_ar ? <li><i className="bi bi-geo-alt me-1" aria-hidden="true" />{inst.region_ar}{inst.address_ar ? ` · ${inst.address_ar}` : ""}</li> : null}
                        {inst.contact_name || inst.contact_phone || inst.contact_email ? (
                            <li>
                                <i className="bi bi-person me-1" aria-hidden="true" />
                                {[inst.contact_name, inst.contact_phone, inst.contact_email].filter(Boolean).join(" · ")}
                            </li>
                        ) : null}
                        {loanRange ? <li><i className="bi bi-cash-coin me-1" aria-hidden="true" />{t("mfi.admin.loan_range", undefined, "نطاق القرض")}: {loanRange}</li> : null}
                        {inst.focus_ar ? <li><i className="bi bi-briefcase me-1" aria-hidden="true" />{inst.focus_ar}</li> : null}
                        {inst.website ? <li><i className="bi bi-link-45deg me-1" aria-hidden="true" /><a href={inst.website} target="_blank" rel="noreferrer">{inst.website}</a></li> : null}
                        {inst.notes ? <li><i className="bi bi-sticky me-1" aria-hidden="true" />{inst.notes}</li> : null}
                    </ul>
                </div>
                <div className="d-flex flex-column align-items-end gap-2">
                    <StatusBadge label={inst.is_active ? t("mfi.admin.active", undefined, "فعّالة") : t("mfi.admin.inactive", undefined, "غير فعّالة")} tone={inst.is_active ? "success" : "warning"} />
                    <button type="button" className="btn btn-outline-secondary btn-sm" style={{ minHeight: "44px" }} onClick={() => { setEditing((v) => !v); if (!editing) { profileForm.setData({ ...fromInstitution(inst), is_active: inst.is_active }); } }}>
                        {editing ? t("mfi.admin.close_edit", undefined, "إغلاق التعديل") : t("mfi.admin.edit_profile", undefined, "تعديل الملف")}
                    </button>
                </div>
            </div>

            {editing ? (
                <form
                    className="border rounded p-3 mb-3 bg-light"
                    onSubmit={(e) => {
                        e.preventDefault();
                        profileForm.put(`/admin/mfis/${inst.public_id}`, {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                        });
                    }}
                >
                    <p className="fw-semibold mb-2">{t("mfi.admin.edit_institution", undefined, "تعديل ملف المؤسسة")}</p>
                    <ProfileFormFields
                        idPrefix={`edit-${inst.public_id}`}
                        data={profileForm.data}
                        errors={profileForm.errors}
                        setData={(key, value) => profileForm.setData(key, value)}
                        regionSuggestions={regionSuggestions}
                    />
                    <div className="form-check mb-3">
                        <input
                            id={`active-${inst.public_id}`}
                            className="form-check-input"
                            type="checkbox"
                            checked={profileForm.data.is_active}
                            onChange={(e) => profileForm.setData("is_active", e.target.checked)}
                        />
                        <label className="form-check-label" htmlFor={`active-${inst.public_id}`}>{t("mfi.admin.active_matching", undefined, "المؤسسة فعّالة وتدخل في المطابقة")}</label>
                    </div>
                    <button type="submit" className="btn btn-primary" style={{ minHeight: "44px" }} disabled={profileForm.processing}>
                        {t("mfi.admin.save_profile", undefined, "حفظ الملف")}
                    </button>
                </form>
            ) : null}

            <div className="row g-3 mt-1">
                <div className="col-12 col-lg-7">
                    <h3 className="h6">{t("mfi.admin.eligibility", undefined, "شروط الأهلية")} <span className="text-body-secondary fw-normal">({activeCriteria} {t("mfi.admin.active_criteria", undefined, "فعّال")})</span></h3>
                    {inst.criteria.length === 0 ? <p className="small text-body-secondary">{t("mfi.admin.no_criteria", undefined, "لا توجد شروط بعد. أضف شرطاً ليظهر في المطابقة.")}</p> : (
                        <ul className="list-unstyled d-flex flex-column gap-2 mb-3">
                            {inst.criteria.map((c) => (
                                <li key={c.id} className="border rounded p-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <span>
                                        <span className="fw-semibold d-block">{c.label_ar}</span>
                                        <span className="small text-body-secondary">{c.type_label}{c.parameters?.min_score !== undefined ? t("mfi.admin.min_threshold", { score: c.parameters.min_score }, ` · الحد ${c.parameters.min_score}`) : ""}</span>
                                    </span>
                                    {c.is_active ? (
                                        <button type="button" className="btn btn-outline-danger btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/mfis/criteria/${c.id}/deactivate`, {}, { preserveScroll: true })}>{t("mfi.admin.deactivate", undefined, "إيقاف")}</button>
                                    ) : <StatusBadge label={t("mfi.admin.paused", undefined, "موقوف")} tone="warning" />}
                                </li>
                            ))}
                        </ul>
                    )}
                    <form className="border rounded p-3" onSubmit={(e) => { e.preventDefault(); criterionForm.post(`/admin/mfis/${inst.public_id}/criteria`, { preserveScroll: true, onSuccess: () => criterionForm.reset() }); }}>
                        <p className="fw-semibold mb-2">{t("mfi.admin.new_criterion", undefined, "شرط جديد")}</p>
                        <TextInput id={`label-${inst.public_id}`} name="label_ar" label={t("mfi.admin.criterion_label", undefined, "وصف الشرط")} value={criterionForm.data.label_ar} onChange={(v) => criterionForm.setData("label_ar", v)} error={criterionForm.errors.label_ar} />
                        <div className="mb-3">
                            <label className="form-label mb-1 fw-semibold" htmlFor={`type-${inst.public_id}`}>{t("mfi.admin.criterion_type", undefined, "نوع الشرط")}</label>
                            <select id={`type-${inst.public_id}`} className="form-select" style={{ minHeight: "48px" }} value={criterionForm.data.type} onChange={(e) => criterionForm.setData("type", e.target.value)}>
                                {Object.entries(criterionTypes).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </select>
                        </div>
                        {criterionForm.data.type === "min_readiness_score" ? (
                            <TextInput id={`min-${inst.public_id}`} name="min_score" type="number" label={t("mfi.admin.min_score", undefined, "الحد الأدنى")} value={criterionForm.data.min_score} onChange={(v) => criterionForm.setData("min_score", v)} error={criterionForm.errors.min_score} />
                        ) : null}
                        {criterionForm.data.type === "compliance_requirement_completed" ? (
                            <div className="mb-3">
                                <label className="form-label mb-1 fw-semibold" htmlFor={`req-${inst.public_id}`}>{t("compliance.enterprise.requirements", undefined, "المتطلب")}</label>
                                <select id={`req-${inst.public_id}`} className="form-select" style={{ minHeight: "48px" }} value={criterionForm.data.compliance_requirement_id} onChange={(e) => criterionForm.setData("compliance_requirement_id", e.target.value)}>
                                    <option value="">{t("mfi.admin.choose_requirement", undefined, "اختر المتطلب")}</option>
                                    {complianceRequirements.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}
                                </select>
                            </div>
                        ) : null}
                        <button type="submit" className="btn btn-primary" style={{ minHeight: "44px" }} disabled={criterionForm.processing}>{t("mfi.admin.add_criterion", undefined, "إضافة شرط")}</button>
                    </form>
                </div>
                <div className="col-12 col-lg-5">
                    <h3 className="h6">{t("mfi.admin.institution_users", undefined, "مستخدمو المؤسسة")}</h3>
                    {inst.users.length === 0 ? <p className="small text-body-secondary">{t("mfi.admin.no_users", undefined, "لا يوجد مستخدم مرتبط. بدون ربط لا يرى أحد حزم الإحالة الخاصة بهذه المؤسسة.")}</p> : (
                        <ul className="list-unstyled d-flex flex-column gap-2 mb-3">
                            {inst.users.map((u) => (
                                <li key={u.id} className="border rounded px-3 py-2">
                                    <span className="d-block fw-semibold">{u.name}</span>
                                    <small className="text-body-secondary">{u.email}</small>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="d-flex flex-column gap-2">
                        <label className="form-label mb-0 fw-semibold" htmlFor={`user-${inst.public_id}`}>{t("mfi.admin.link_user", undefined, "ربط مستخدم")}</label>
                        <select id={`user-${inst.public_id}`} className="form-select" style={{ minHeight: "44px" }} value={userId} onChange={(e) => setUserId(e.target.value)}>
                            <option value="">{t("mfi.admin.choose_mfi_user", undefined, "اختر مستخدم مؤسسة تمويل")}</option>
                            {mfiUsers.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                        </select>
                        <button type="button" className="btn btn-outline-primary" style={{ minHeight: "44px" }} disabled={!userId} onClick={() => router.post(`/admin/mfis/${inst.public_id}/users`, { user_id: userId }, { preserveScroll: true, onSuccess: () => setUserId("") })}>{t("mfi.admin.link_user_submit", undefined, "ربط المستخدم")}</button>
                    </div>
                </div>
            </div>
        </article>
    );
}

export default function Admin(props: Props) {
    const t = useT();
    const form = useForm(emptyProfile());
    const active = props.institutions.filter((i) => i.is_active).length;
    const withContact = props.institutions.filter((i) => i.contact_phone || i.contact_email).length;

    return (
        <AppLayout title={t("mfi.admin.page_title", undefined, "مؤسسات التمويل")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("mfi.admin.page_heading", undefined, "دليل مؤسسات التمويل الأصغر")}
                    description={t("mfi.admin.page_description", undefined, "عرّف ملف المؤسسة (التواصل، التغطية، المنتجات)، ثم أضف شروط الأهلية واربط مستخدميها. المطابقة تستخدم الشروط ولا تعني موافقة تمويل.")}
                />
                <div className="row g-3">
                    <div className="col-6 col-md-4">
                        <StatCard label={t("mfi.admin.stat_institutions", undefined, "المؤسسات")} value={String(props.institutions.length)} />
                    </div>
                    <div className="col-6 col-md-4">
                        <StatCard label={t("mfi.admin.active", undefined, "فعّالة")} value={String(active)} hint={t("mfi.admin.stat_active_hint", undefined, "تدخل في المطابقة")} />
                    </div>
                    <div className="col-12 col-md-4">
                        <StatCard label={t("mfi.admin.stat_with_contact", undefined, "بجهة تواصل")} value={String(withContact)} hint={t("mfi.admin.stat_contact_hint", undefined, "هاتف أو بريد مسجّل")} />
                    </div>
                </div>
                <SectionCard title={t("mfi.admin.new_institution", undefined, "مؤسسة جديدة")}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/admin/mfis", { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                    >
                        <ProfileFormFields
                            idPrefix="new"
                            data={form.data}
                            errors={form.errors}
                            setData={(key, value) => form.setData(key, value)}
                            regionSuggestions={props.regionSuggestions}
                        />
                        <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                            {t("mfi.admin.add_institution", undefined, "إضافة المؤسسة")}
                        </button>
                    </form>
                </SectionCard>
                {props.institutions.length === 0 ? <EmptyState title={t("mfi.admin.empty", undefined, "لا توجد مؤسسات بعد")} description={t("mfi.admin.empty_hint", undefined, "أضف أول مؤسسة تمويل بملف كامل، ثم حدّد شروط الأهلية.")} /> : null}
                {props.institutions.map((inst) => (
                    <InstitutionCard
                        key={inst.public_id}
                        inst={inst}
                        criterionTypes={props.criterionTypes}
                        complianceRequirements={props.complianceRequirements}
                        mfiUsers={props.mfiUsers}
                        regionSuggestions={props.regionSuggestions}
                    />
                ))}
            </div>
        </AppLayout>
    );
}
