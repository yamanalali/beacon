import { useRef, useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { useT } from "../../Hooks/useT";
import { PageHeader } from "../../Components/Common/PageHeader";
import { StatCard } from "../../Components/Common/StatCard";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";
import { R, mutate } from "../../Components/Readiness/adminApi";
import { RuleSetBuilder } from "../../Components/Readiness/RuleSetBuilder";
import type { RuleSetDraft } from "../../Components/Readiness/types";

/** Props of ReadinessAdminController@index (backend #74). */
type AdminRuleSet = RuleSetDraft & { status_label: string; published_at: string | null };
type Props = { ruleSets: AdminRuleSet[]; treatments: Record<string, string>; complianceRequirements: { id: number; code: string }[] };

const tone = (s: string) => (s === "draft" ? "warning" : s === "published" ? "success" : "neutral");

function NewDraftForm({ hasDraft }: { hasDraft: boolean }) {
    const t = useT();
    const [name, setName] = useState("");
    const [summary, setSummary] = useState("");
    const [copy, setCopy] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    return (
        <form
            onSubmit={async (e) => {
                e.preventDefault();
                setBusy(true);
                const r = await mutate("POST", R.store(), { name_ar: name, change_summary: summary || null, copy_from_current: copy });
                setBusy(false);
                if (r.ok) { setName(""); setSummary(""); setErrors({}); setMessage(null); return; }
                setErrors(r.errors);
                setMessage(Object.keys(r.errors).length ? null : r.message);
            }}
        >
            {hasDraft ? <div className="alert alert-info py-2" role="status">{t("readiness.admin.open_draft", undefined, "توجد مسودة مفتوحة. انشرها أو احذفها قبل إنشاء مسودة جديدة.")}</div> : null}
            {message ? <div className="alert alert-danger py-2" role="alert">{message}</div> : null}
            <TextInput id="rs-name" name="name_ar" label={t("readiness.admin.ruleset_name", undefined, "اسم مجموعة القواعد")} value={name} onChange={setName} error={errors.name_ar} />
            <TextInput id="rs-summary" name="change_summary" label={t("readiness.admin.change_summary", undefined, "ملخص التغيير (اختياري)")} value={summary} onChange={setSummary} error={errors.change_summary} />
            <div className="form-check mb-3">
                <input id="rs-copy" type="checkbox" className="form-check-input" checked={copy} onChange={(e) => setCopy(e.target.checked)} />
                <label className="form-check-label" htmlFor="rs-copy">{t("readiness.admin.copy_current", undefined, "ابدأ بنسخة من القواعد المعتمدة الحالية")}</label>
            </div>
            <button type="submit" className="btn btn-primary" disabled={busy || !name.trim()}>{t("readiness.admin.create_draft", undefined, "إنشاء مسودة")}</button>
        </form>
    );
}

function TemplatesCard({ hasDraft }: { hasDraft: boolean }) {
    const t = useT();
    const fileRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    async function importFile(file: File) {
        setBusy(true);
        setMessage(null);
        const form = new FormData();
        form.append("file", file);
        const r = await mutate("POST", R.import(), form);
        setBusy(false);
        if (!r.ok) setMessage(Object.values(r.errors)[0] ?? r.message);
    }

    return (
        <SectionCard title={t("readiness.admin.templates", undefined, "قوالب وأمثلة")}>
            <p className="small text-body-secondary">
                {t("readiness.admin.templates_hint", undefined, "نزّل مثالاً جاهزاً أو قالباً فارغاً في Excel، عدّله مع فريقك، ثم ارفعه هنا ليصبح مسودة. المثال تعليمي فقط وليس قواعد معتمدة من بيكون.")}
            </p>
            <div className="d-flex flex-wrap gap-2 mb-3">
                <a className="btn btn-outline-primary" href="/admin/readiness/templates/excel-example" download>
                    <i className="bi bi-file-earmark-excel me-1" aria-hidden="true" />
                    {t("readiness.admin.excel_example", undefined, "مثال Excel معبأ")}
                </a>
                <a className="btn btn-outline-primary" href="/admin/readiness/templates/excel-blank" download>
                    <i className="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true" />
                    {t("readiness.admin.excel_blank", undefined, "قالب Excel فارغ")}
                </a>
                <a className="btn btn-outline-primary" href="/admin/readiness/templates/json-example" download>
                    <i className="bi bi-filetype-json me-1" aria-hidden="true" />
                    {t("readiness.admin.json_example", undefined, "مثال JSON للاستيراد")}
                </a>
            </div>
            {hasDraft ? (
                <div className="alert alert-info py-2 mb-0" role="status">{t("readiness.admin.import_blocked", undefined, "انشر المسودة الحالية أو احذفها قبل استيراد ملف جديد.")}</div>
            ) : (
                <>
                    <input
                        ref={fileRef}
                        type="file"
                        className="d-none"
                        accept=".xlsx,.xlsm,application/json,.json"
                        aria-label={t("readiness.admin.import_file_label", undefined, "ملف قواعد Excel أو JSON")}
                        onChange={(e) => {
                            const f = e.target.files?.[0];
                            if (f) void importFile(f);
                            e.target.value = "";
                        }}
                    />
                    <button type="button" className="btn btn-primary" style={{ minHeight: "44px" }} disabled={busy} onClick={() => fileRef.current?.click()}>
                        {busy ? <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" /> : <i className="bi bi-upload me-1" aria-hidden="true" />}
                        {t("readiness.admin.import", undefined, "استيراد Excel أو JSON")}
                    </button>
                    {message ? <div className="alert alert-danger py-2 mt-3 mb-0" role="alert">{message}</div> : null}
                </>
            )}
        </SectionCard>
    );
}

export default function Admin({ ruleSets }: Props) {
    const t = useT();
    const initial = ruleSets.find((s) => s.status === "draft") ?? ruleSets.find((s) => s.status === "published") ?? ruleSets[0];
    const [selectedId, setSelectedId] = useState<number | null>(initial?.id ?? null);
    const selected = ruleSets.find((s) => s.id === selectedId) ?? initial ?? null;
    const hasDraft = ruleSets.some((s) => s.status === "draft");

    const published = ruleSets.filter((s) => s.status === "published").length;

    return (
        <AppLayout title={t("readiness.admin.title", undefined, "قواعد الجاهزية")}>
            <div className="app-page d-flex flex-column gap-3">
            <PageHeader title={t("readiness.admin.title", undefined, "قواعد الجاهزية")} description={t("readiness.admin.description", undefined, "ابنِ المسودة، راجع المعاينة، ثم اعتمد النسخة. النسخ المعتمدة تبقى مجمّدة، والتقييمات السابقة تحتفظ بنسختها.")} />
            <div className="row g-3">
                <div className="col-6 col-md-3"><StatCard label={t("readiness.admin.versions", undefined, "النسخ")} value={String(ruleSets.length)} icon="bi-layers" /></div>
                <div className="col-6 col-md-3"><StatCard label={t("readiness.admin.published", undefined, "معتمدة")} value={String(published)} hint={t("readiness.admin.frozen", undefined, "مجمّدة")} icon="bi-shield-check" tone="blue" /></div>
                <div className="col-12 col-md-6"><StatCard label={t("readiness.admin.next_step", undefined, "الخطوة التالية")} value={hasDraft ? t("readiness.admin.review_draft", undefined, "مراجعة المسودة") : t("readiness.admin.create_draft", undefined, "إنشاء مسودة")} hint={hasDraft ? t("readiness.admin.draft_hint", undefined, "انشرها أو احذفها قبل مسودة جديدة") : t("readiness.admin.copy_current", undefined, "ابدأ من القواعد المعتمدة الحالية")} icon="bi-signpost" tone="amber" /></div>
            </div>
            <div className="row g-3">
                <div className="col-12 col-lg-3 d-flex flex-column gap-3">
                    <SectionCard title={t("readiness.admin.versions", undefined, "النسخ")}>
                        {ruleSets.length === 0 ? (
                            <EmptyState title={t("readiness.admin.empty", undefined, "لا توجد قواعد بعد")} description={t("readiness.admin.empty_hint", undefined, "أنشئ مسودة أولى لبدء إعداد مصفوفة التقييم.")} />
                        ) : (
                            <ul className="list-unstyled d-flex flex-column gap-2 mb-0">
                                {ruleSets.map((s) => (
                                    <li key={s.id}>
                                        <button type="button" className={`app-mobile-record w-100 text-start border-0${selected?.id === s.id ? " active" : ""}`} aria-pressed={selected?.id === s.id} onClick={() => setSelectedId(s.id)}>
                                            <span className="d-flex flex-wrap align-items-center gap-2">
                                                <span className="fw-semibold flex-grow-1">{s.name_ar}</span>
                                                <StatusBadge label={s.status_label} tone={tone(s.status)} />
                                            </span>
                                            <span className="small text-muted">v<bdi dir="ltr">{s.version_number}</bdi></span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                    <SectionCard title={t("readiness.admin.new_draft_section", undefined, "مسودة جديدة")}><NewDraftForm hasDraft={hasDraft} /></SectionCard>
                    <TemplatesCard hasDraft={hasDraft} />
                </div>
                <div className="col-12 col-lg-9">
                    {selected ? <RuleSetBuilder key={selected.id} ruleSet={selected} /> : null}
                </div>
            </div>
            </div>
        </AppLayout>
    );
}
