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
import { VersionForm, emptyVersionValues } from "../../Components/Compliance/VersionForm";

type Version = {
    id: number;
    version_number: number;
    title_ar: string;
    description_ar: string | null;
    guidance_ar: string | null;
    deadline_note_ar: string | null;
    guidance_pending: boolean;
    requires_document: boolean;
    effective_from: string | null;
    status: "draft" | "published";
    change_summary: string | null;
    published_by: string | null;
    published_at: string | null;
};

type Requirement = {
    public_id: string;
    code: string;
    category_label: string;
    is_active: boolean;
    replaced_by: string | null;
    versions: Version[];
};

type Props = { requirements: Requirement[]; categories: Record<string, string> };

function RequirementCard({ req, others }: { req: Requirement; others: Requirement[] }) {
    const t = useT();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [newVersion, setNewVersion] = useState(false);
    const [replacing, setReplacing] = useState(false);
    const [replacement, setReplacement] = useState("");
    const hasDraft = req.versions.some((v) => v.status === "draft");
    const latest = [...req.versions].sort((a, b) => b.version_number - a.version_number)[0];
    const url = `/admin/compliance/${req.public_id}`;

    return (
        <article className="border rounded p-3 bg-white">
            <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div className="small text-body-secondary">{req.category_label}</div>
                    <h2 className="h5 mb-0"><bdi dir="ltr">{req.code}</bdi></h2>
                </div>
                <StatusBadge label={req.is_active ? t("compliance.admin.status_active", undefined, "فعّال") : req.replaced_by ? t("compliance.admin.replaced_by", { code: req.replaced_by }, `مستبدل بـ ${req.replaced_by}`) : t("compliance.admin.status_inactive", undefined, "غير فعّال")} tone={req.is_active ? "success" : "warning"} />
            </div>
            <ul className="list-unstyled d-flex flex-column gap-2 mt-3 mb-0">
                {req.versions.map((v) => (
                    <li key={v.id} className="border rounded p-3">
                        <div className="d-flex justify-content-between flex-wrap gap-2">
                            <span className="fw-semibold">{t("compliance.admin.version_line", { n: v.version_number, title: v.title_ar }, `نسخة ${v.version_number}: ${v.title_ar}`)}</span>
                            <StatusBadge label={v.status === "published" ? t("compliance.admin.version_published", undefined, "منشورة") : t("common.draft", undefined, "مسودة")} tone={v.status === "published" ? "success" : "warning"} />
                        </div>
                        <div className="d-flex flex-wrap gap-2 mt-2">
                            {v.requires_document ? <StatusBadge label={t("compliance.admin.requires_document", undefined, "يتطلب وثيقة")} tone="neutral" /> : null}
                            {v.effective_from ? <span className="small text-body-secondary">{t("compliance.admin.effective", undefined, "السريان")} <bdi dir="ltr">{v.effective_from}</bdi></span> : null}
                        </div>
                        {v.guidance_pending ? <p className="small text-body-secondary mb-0 mt-2">{t("compliance.admin.guidance_pending", undefined, "الإرشاد بانتظار مدخلات بيكون")}</p> : null}
                        {v.published_at ? <p className="small mb-0 mt-2">{t("compliance.admin.published_by", { user: v.published_by ?? "", at: v.published_at }, `نشرها ${v.published_by} في ${v.published_at}`)}</p> : null}
                        {v.change_summary ? <p className="small mb-0 mt-1">{t("compliance.admin.change_summary", { summary: v.change_summary }, `التغيير: ${v.change_summary}`)}</p> : null}
                        {v.status === "draft" ? (
                            editingId === v.id ? (
                                <VersionForm
                                    idPrefix={`edit-${v.id}`}
                                    method="put"
                                    url={`/admin/compliance/versions/${v.id}`}
                                    initial={{ title_ar: v.title_ar, description_ar: v.description_ar ?? "", guidance_ar: v.guidance_ar ?? "", deadline_note_ar: v.deadline_note_ar ?? "", effective_from: v.effective_from ?? "", requires_document: v.requires_document, change_summary: v.change_summary ?? "" }}
                                    submitLabel={t("compliance.admin.save_draft_changes", undefined, "حفظ تعديلات المسودة")}
                                    onDone={() => setEditingId(null)}
                                />
                            ) : (
                                <div className="d-flex flex-wrap gap-2 mt-3">
                                    <button type="button" className="btn btn-outline-primary btn-sm" style={{ minHeight: "44px" }} onClick={() => { setEditingId(v.id); setNewVersion(false); setReplacing(false); }}>
                                        {t("compliance.admin.edit_draft", undefined, "تعديل المسودة")}
                                    </button>
                                    <button type="button" className="btn btn-primary btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`/admin/compliance/versions/${v.id}/publish`, {}, { preserveScroll: true })}>
                                        {t("compliance.admin.publish_version", undefined, "نشر النسخة")}
                                    </button>
                                </div>
                            )
                        ) : null}
                    </li>
                ))}
            </ul>
            {newVersion ? (
                <VersionForm
                    idPrefix={`new-${req.public_id}`}
                    method="post"
                    url={`${url}/versions`}
                    initial={{
                        ...emptyVersionValues(),
                        title_ar: latest?.title_ar ?? "",
                        description_ar: latest?.description_ar ?? "",
                        guidance_ar: latest?.guidance_ar ?? "",
                        deadline_note_ar: latest?.deadline_note_ar ?? "",
                        requires_document: latest?.requires_document ?? false,
                    }}
                    requireSummary
                    submitLabel={t("compliance.admin.save_new_version", undefined, "حفظ النسخة الجديدة كمسودة")}
                    onDone={() => setNewVersion(false)}
                />
            ) : null}
            {replacing ? (
                <div className="border rounded p-3 mt-3 bg-light">
                    <label htmlFor={`replace-${req.public_id}`} className="form-label fw-semibold">{t("compliance.admin.replacement_label", undefined, "المتطلب البديل")}</label>
                    <select id={`replace-${req.public_id}`} className="form-select mb-2" style={{ minHeight: "48px" }} value={replacement} onChange={(e) => setReplacement(e.target.value)}>
                        <option value="">{t("compliance.admin.choose_replacement", undefined, "اختر المتطلب البديل")}</option>
                        {others.map((o) => <option key={o.public_id} value={o.public_id}>{o.code}</option>)}
                    </select>
                    <p className="small text-body-secondary">{t("compliance.admin.replace_hint", undefined, "يتوقف هذا المتطلب ويُسجَّل بديله. التقييمات السابقة تبقى كما هي.")}</p>
                    <div className="d-flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="btn btn-primary"
                            style={{ minHeight: "44px" }}
                            disabled={!replacement}
                            onClick={() => {
                                if (window.confirm(t("compliance.admin.replace_confirm", undefined, "هل تريد استبدال هذا المتطلب؟ سيتوقف عن الظهور للمنشآت."))) {
                                    router.post(`${url}/replace`, { replacement_public_id: replacement }, { preserveScroll: true, onSuccess: () => { setReplacing(false); setReplacement(""); } });
                                }
                            }}
                        >
                            {t("compliance.admin.confirm_replace", undefined, "استبدال المتطلب")}
                        </button>
                        <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "44px" }} onClick={() => setReplacing(false)}>{t("common.cancel", undefined, "إلغاء")}</button>
                    </div>
                </div>
            ) : null}
            <div className="d-flex flex-wrap gap-2 mt-3">
                {!hasDraft && !newVersion ? (
                    <button type="button" className="btn btn-outline-primary btn-sm" style={{ minHeight: "44px" }} onClick={() => { setNewVersion(true); setEditingId(null); setReplacing(false); }}>
                        {t("compliance.admin.new_version", undefined, "إنشاء نسخة جديدة")}
                    </button>
                ) : null}
                {req.is_active && others.length > 0 && !replacing ? (
                    <button type="button" className="btn btn-outline-secondary btn-sm" style={{ minHeight: "44px" }} onClick={() => { setReplacing(true); setNewVersion(false); setEditingId(null); }}>
                        {t("compliance.admin.replace", undefined, "استبدال بمتطلب آخر")}
                    </button>
                ) : null}
                {req.is_active ? (
                    <button type="button" className="btn btn-outline-danger btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`${url}/deactivate`, {}, { preserveScroll: true })}>
                        {t("compliance.admin.deactivate", undefined, "إيقاف المتطلب")}
                    </button>
                ) : (
                    <button type="button" className="btn btn-outline-success btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`${url}/reactivate`, {}, { preserveScroll: true })}>
                        {t("compliance.admin.reactivate", undefined, "إعادة تفعيل المتطلب")}
                    </button>
                )}
            </div>
        </article>
    );
}

export default function Admin({ requirements, categories }: Props) {
    const t = useT();
    const form = useForm({
        code: "",
        category: Object.keys(categories)[0] ?? "",
        title_ar: "",
        description_ar: "",
        guidance_ar: "",
        requires_document: false,
        effective_from: "",
    });

    const active = requirements.filter((r) => r.is_active).length;
    const drafts = requirements.reduce((n, r) => n + r.versions.filter((v) => v.status === "draft").length, 0);

    return (
        <AppLayout title={t("compliance.title", undefined, "متطلبات الامتثال")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("compliance.admin.title", undefined, "إدارة متطلبات الامتثال")}
                    description={t("compliance.admin.description", undefined, "أضف المتطلب كمسودة، ثم انشر النسخة عندما يصبح الإرشاد جاهزاً. التقييمات السابقة تبقى على النسخة التي استُخدمت وقتها.")}
                />

                <div className="row g-3">
                    <div className="col-12 col-md-4">
                        <StatCard label={t("compliance.admin.stat_requirements", undefined, "المتطلبات")} value={String(requirements.length)} hint={t("compliance.admin.stat_requirements_hint", undefined, "كل المتطلبات المسجلة")} icon="bi-list-check" />
                    </div>
                    <div className="col-6 col-md-4">
                        <StatCard label={t("compliance.admin.stat_active", undefined, "فعّالة")} value={String(active)} hint={t("compliance.admin.stat_active_hint", undefined, "تظهر للمنشآت")} icon="bi-check2-circle" tone="blue" />
                    </div>
                    <div className="col-6 col-md-4">
                        <StatCard label={t("compliance.admin.stat_drafts", undefined, "مسودات")} value={String(drafts)} hint={t("compliance.admin.stat_drafts_hint", undefined, "بانتظار النشر")} icon="bi-pencil" tone="amber" />
                    </div>
                </div>

                <div className="row g-3 align-items-start">
                    <div className="col-12 col-lg-4">
                        <SectionCard title={t("compliance.admin.new_requirement", undefined, "متطلب جديد")}>
                            <p className="small text-body-secondary">{t("compliance.admin.new_requirement_hint", undefined, "يُحفظ كمسودة. لا يظهر للمنشأة قبل نشر النسخة.")}</p>
                            <form
                                className="d-flex flex-column gap-1"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post("/admin/compliance", { preserveScroll: true, onSuccess: () => form.reset() });
                                }}
                            >
                                <TextInput id="code" name="code" label={t("compliance.admin.code", undefined, "الرمز")} value={form.data.code} onChange={(v) => form.setData("code", v)} error={form.errors.code} />
                                <div className="mb-3">
                                    <label htmlFor="category" className="form-label mb-1 fw-semibold">{t("compliance.admin.category", undefined, "التصنيف")}</label>
                                    <select id="category" className="form-select" style={{ minHeight: "48px" }} value={form.data.category} onChange={(e) => form.setData("category", e.target.value)}>
                                        {Object.entries(categories).map(([key, label]) => (
                                            <option key={key} value={key}>{label}</option>
                                        ))}
                                    </select>
                                </div>
                                <TextInput id="title_ar" name="title_ar" label={t("documents.show.title_label", undefined, "العنوان")} value={form.data.title_ar} onChange={(v) => form.setData("title_ar", v)} error={form.errors.title_ar} />
                                <TextInput id="description_ar" name="description_ar" label={t("compliance.admin.description", undefined, "الوصف")} value={form.data.description_ar} onChange={(v) => form.setData("description_ar", v)} />
                                <TextInput
                                    id="guidance_ar"
                                    name="guidance_ar"
                                    label={t("compliance.admin.guidance", undefined, "الإرشاد المعتمد")}
                                    value={form.data.guidance_ar}
                                    onChange={(v) => form.setData("guidance_ar", v)}
                                    help={t("compliance.admin.guidance_help", undefined, "اتركه فارغاً إذا كان المحتوى بانتظار اعتماد بيكون.")}
                                />
                                <TextInput id="effective_from" name="effective_from" type="date" label={t("compliance.admin.effective_from", undefined, "تاريخ السريان")} value={form.data.effective_from} onChange={(v) => form.setData("effective_from", v)} />
                                <div className="form-check mb-3">
                                    <input id="requires_document" type="checkbox" className="form-check-input" checked={form.data.requires_document} onChange={(e) => form.setData("requires_document", e.target.checked)} />
                                    <label htmlFor="requires_document" className="form-check-label">{t("compliance.admin.requires_document", undefined, "يتطلب وثيقة")}</label>
                                </div>
                                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>{t("compliance.admin.save_draft", undefined, "حفظ كمسودة")}</button>
                            </form>
                        </SectionCard>
                    </div>

                    <div className="col-12 col-lg-8 d-flex flex-column gap-3">
                        {requirements.length === 0 ? <EmptyState title={t("compliance.admin.empty", undefined, "لا توجد متطلبات بعد")} description={t("compliance.admin.empty_hint", undefined, "أضف أول متطلب من النموذج، ثم انشر النسخة عندما يعتمد بيكون الإرشاد.")} /> : null}
                        {requirements.map((req) => (
                            <RequirementCard key={req.public_id} req={req} others={requirements.filter((r) => r.public_id !== req.public_id)} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
