import { useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
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
            {hasDraft ? <div className="alert alert-info py-2" role="status">توجد مسودة مفتوحة. انشرها أو احذفها قبل إنشاء مسودة جديدة.</div> : null}
            {message ? <div className="alert alert-danger py-2" role="alert">{message}</div> : null}
            <TextInput id="rs-name" name="name_ar" label="اسم مجموعة القواعد" value={name} onChange={setName} error={errors.name_ar} />
            <TextInput id="rs-summary" name="change_summary" label="ملخص التغيير (اختياري)" value={summary} onChange={setSummary} error={errors.change_summary} />
            <div className="form-check mb-3">
                <input id="rs-copy" type="checkbox" className="form-check-input" checked={copy} onChange={(e) => setCopy(e.target.checked)} />
                <label className="form-check-label" htmlFor="rs-copy">ابدأ بنسخة من القواعد المعتمدة الحالية</label>
            </div>
            <button type="submit" className="btn btn-primary" disabled={busy || !name.trim()}>إنشاء مسودة</button>
        </form>
    );
}

export default function Admin({ ruleSets }: Props) {
    const initial = ruleSets.find((s) => s.status === "draft") ?? ruleSets.find((s) => s.status === "published") ?? ruleSets[0];
    const [selectedId, setSelectedId] = useState<number | null>(initial?.id ?? null);
    const selected = ruleSets.find((s) => s.id === selectedId) ?? initial ?? null;
    const hasDraft = ruleSets.some((s) => s.status === "draft");

    return (
        <AppLayout title="قواعد الجاهزية">
            <PageHeader title="قواعد الجاهزية" description="محاور ومعايير وأوزان وشرائح تقييم جاهزية المنشآت. النسخ المعتمدة مجمّدة." />
            <div className="row g-3">
                <div className="col-12 col-lg-3 d-flex flex-column gap-3">
                    <SectionCard title="النسخ">
                        {ruleSets.length === 0 ? (
                            <EmptyState title="لا توجد قواعد بعد" description="أنشئ مسودة أولى لبدء إعداد مصفوفة التقييم." />
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
                    <SectionCard title="مسودة جديدة"><NewDraftForm hasDraft={hasDraft} /></SectionCard>
                </div>
                <div className="col-12 col-lg-9">
                    {selected ? <RuleSetBuilder key={selected.id} ruleSet={selected} /> : null}
                </div>
            </div>
        </AppLayout>
    );
}
