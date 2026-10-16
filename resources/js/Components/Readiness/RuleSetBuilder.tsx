import { useRef, useState, type DragEvent } from "react";
import type { Criterion, Pillar, ScoreBand } from "../../types/readiness";
import { SectionCard } from "../Common/SectionCard";
import { StatusBadge } from "../Feedback/StatusBadge";
import { BandsEditor } from "./BandsEditor";
import { CriterionDrawer } from "./CriterionDrawer";
import { PreviewPanel } from "./PreviewPanel";
import type { PreviewFetcher } from "./previewApi";
import { CRITERION_TYPES, defaultConfig, exportRuleSet, importRuleSet, num, reorder, sortByOrder, validateRuleSet, type ValidationIssue } from "./scoring";
import type { RuleSetDraft } from "./types";

type Props = {
    ruleSet: RuleSetDraft;
    /** Called with the edited draft. No v2 save endpoint exists yet; the page decides what to do. */
    onSave?: (rs: RuleSetDraft) => void;
    /** Duplicate as a new draft (existing #4: POST /admin/readiness with copy_from_current). */
    onDuplicate?: (rs: RuleSetDraft) => void;
    previewFetcher?: PreviewFetcher | null;
};

const typeLabel = (t: string) => CRITERION_TYPES.find((x) => x.type === t)?.label_ar ?? t;
let localId = -1000;
const nextId = () => --localId;

function useDragList(onMove: (from: number, to: number) => void) {
    const from = useRef<number | null>(null);
    return (i: number) => ({
        draggable: true,
        onDragStart: (e: DragEvent) => { from.current = i; e.dataTransfer.effectAllowed = "move"; },
        onDragOver: (e: DragEvent) => e.preventDefault(),
        onDrop: (e: DragEvent) => { e.preventDefault(); if (from.current !== null) onMove(from.current, i); from.current = null; },
    });
}

function MoveButtons({ index, count, onMove, label }: { index: number; count: number; onMove: (from: number, to: number) => void; label: string }) {
    return (
        <span className="d-inline-flex gap-1">
            <button type="button" className="app-icon-button" aria-label={`نقل ${label} للأعلى`} disabled={index === 0} onClick={() => onMove(index, index - 1)}><i className="bi bi-arrow-up" aria-hidden="true" /></button>
            <button type="button" className="app-icon-button" aria-label={`نقل ${label} للأسفل`} disabled={index === count - 1} onClick={() => onMove(index, index + 1)}><i className="bi bi-arrow-down" aria-hidden="true" /></button>
        </span>
    );
}

function CriteriaList({ criteria, readOnly, onEdit, onMove, onDelete }: { criteria: Criterion[]; readOnly: boolean; onEdit: (c: Criterion) => void; onMove: (from: number, to: number) => void; onDelete: (c: Criterion) => void }) {
    const drag = useDragList(onMove);
    if (criteria.length === 0) return <p className="text-muted mb-0">لا توجد معايير في هذا المحور.</p>;
    return (
        <ul className="list-unstyled d-flex flex-column gap-2 mb-0">
            {criteria.map((c, i) => (
                <li key={c.id} className="app-mobile-record py-2" data-testid="criterion-row" {...(readOnly ? {} : drag(i))}>
                    <div className="d-flex flex-wrap align-items-center gap-2">
                        {!readOnly ? <i className="bi bi-grip-vertical text-muted" aria-hidden="true" style={{ cursor: "grab" }} /> : null}
                        <span className="fw-semibold flex-grow-1">{c.label_ar || c.code || "معيار بلا اسم"}</span>
                        <StatusBadge label={typeLabel(c.type)} tone="neutral" />
                        {c.is_mandatory ? <StatusBadge label="إلزامي" tone="danger" /> : null}
                        <span className="small text-muted">الوزن <bdi dir="ltr">{String(c.weight)}</bdi></span>
                        {!readOnly ? <MoveButtons index={i} count={criteria.length} onMove={onMove} label={c.label_ar || c.code} /> : null}
                        <button type="button" className="app-icon-button" aria-label={`${readOnly ? "عرض" : "تعديل"} ${c.label_ar || c.code}`} onClick={() => onEdit(c)}><i className={`bi ${readOnly ? "bi-eye" : "bi-pencil"}`} aria-hidden="true" /></button>
                        {!readOnly ? <button type="button" className="app-icon-button" aria-label={`حذف ${c.label_ar || c.code}`} onClick={() => onDelete(c)}><i className="bi bi-trash" aria-hidden="true" /></button> : null}
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function RuleSetBuilder({ ruleSet, onSave, onDuplicate, previewFetcher }: Props) {
    const [rs, setRs] = useState<RuleSetDraft>(ruleSet);
    const [editing, setEditing] = useState<Criterion | null>(null);
    const [tab, setTab] = useState<"matrix" | "bands" | "preview">("matrix");
    const [importMsg, setImportMsg] = useState<{ error?: string; issues?: ValidationIssue[] } | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const readOnly = rs.status !== "draft";
    const issues = validateRuleSet(rs);
    const pillars = sortByOrder(rs.pillars);

    const setPillars = (ps: Pillar[]) => setRs((r) => ({ ...r, pillars: ps }));
    const patchPillar = (id: number, patch: Partial<Pillar>) => setPillars(rs.pillars.map((p) => (p.id === id ? { ...p, ...patch } : p)));
    const movePillar = (from: number, to: number) => setPillars(reorder(rs.pillars, from, to));
    const dragPillar = useDragList(movePillar);
    const inPillar = (pid: number | null) => sortByOrder(rs.criteria.filter((c) => (pid === null ? c.readiness_pillar_id === null || !rs.pillars.some((p) => p.id === c.readiness_pillar_id) : c.readiness_pillar_id === pid)));
    const moveCriterion = (pid: number | null) => (from: number, to: number) => {
        const moved = reorder(inPillar(pid), from, to);
        const byId = new Map(moved.map((c) => [c.id, c]));
        setRs((r) => ({ ...r, criteria: r.criteria.map((c) => byId.get(c.id) ?? c) }));
    };
    const saveCriterion = (c: Criterion) => {
        setRs((r) => {
            const exists = r.criteria.some((x) => x.id === c.id);
            const sort_order = exists ? c.sort_order : r.criteria.filter((x) => x.readiness_pillar_id === c.readiness_pillar_id).length;
            return { ...r, criteria: exists ? r.criteria.map((x) => (x.id === c.id ? c : x)) : [...r.criteria, { ...c, sort_order }] };
        });
        setEditing(null);
    };
    const newCriterion = (pid: number | null): Criterion => ({
        id: nextId(), readiness_rule_set_id: rs.id, readiness_pillar_id: pid, code: "", label_ar: "", description_ar: null,
        // Weight and missing-data treatment are Beacon decisions: weight starts empty (fails validation until set).
        // TODO: BUSINESS DECISION REQUIRED - default missing-data treatment for new criteria (placeholder: needs_review, the most conservative).
        weight: "", is_mandatory: false, missing_data_treatment: "needs_review", compliance_requirement_id: null, sort_order: 0, ...defaultConfig("boolean"),
    }) as Criterion;
    const addPillar = () => setPillars([...rs.pillars, { id: nextId(), readiness_rule_set_id: rs.id, code: "", label_ar: "", weight: "", min_score: null, sort_order: rs.pillars.length }]);
    const removePillar = (p: Pillar) => setRs((r) => ({ ...r, pillars: r.pillars.filter((x) => x.id !== p.id), criteria: r.criteria.map((c) => (c.readiness_pillar_id === p.id ? { ...c, readiness_pillar_id: null } : c)) }));

    const doExport = () => {
        const blob = new Blob([JSON.stringify(exportRuleSet(rs), null, 2)], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `readiness-rules-v${rs.version_number}.json`;
        a.click();
        URL.revokeObjectURL(url);
    };
    const doImport = async (file: File) => {
        const res = importRuleSet(await file.text(), { id: rs.id, version_number: rs.version_number });
        if (!res.ok) return setImportMsg({ error: res.error });
        setRs(res.draft);
        setImportMsg({ issues: res.issues });
    };

    const pillarSection = (p: Pillar | null, i: number) => {
        const pid = p?.id ?? null;
        const crit = inPillar(pid);
        if (!p && crit.length === 0) return null;
        return (
            <div key={pid ?? "implicit"} className="app-mobile-record" data-testid="pillar-card" {...(p && !readOnly ? dragPillar(i) : {})}>
                <div className="fw-semibold d-flex flex-wrap align-items-center gap-2">
                    {p && !readOnly ? <i className="bi bi-grip-vertical text-muted" aria-hidden="true" style={{ cursor: "grab" }} /> : null}
                    <span className="flex-grow-1">{p ? p.label_ar || p.code || "محور بلا اسم" : "معايير غير مصنفة"}</span>
                    {p && !readOnly ? <MoveButtons index={i} count={pillars.length} onMove={movePillar} label={p.label_ar || "المحور"} /> : null}
                    {p && !readOnly ? <button type="button" className="app-icon-button" aria-label={`حذف المحور ${p.label_ar || p.code}`} onClick={() => removePillar(p)}><i className="bi bi-trash" aria-hidden="true" /></button> : null}
                </div>
                {p ? (
                    <div className="row g-2 mb-3">
                        <div className="col-6 col-md-3"><label className="form-label" htmlFor={`p-code-${p.id}`}>الرمز</label><input id={`p-code-${p.id}`} dir="ltr" className="form-control" value={p.code} disabled={readOnly} onChange={(e) => patchPillar(p.id, { code: e.target.value })} /></div>
                        <div className="col-6 col-md-4"><label className="form-label" htmlFor={`p-label-${p.id}`}>اسم المحور</label><input id={`p-label-${p.id}`} className="form-control" value={p.label_ar} disabled={readOnly} onChange={(e) => patchPillar(p.id, { label_ar: e.target.value })} /></div>
                        <div className="col-6 col-md-2"><label className="form-label" htmlFor={`p-weight-${p.id}`}>الوزن</label><input id={`p-weight-${p.id}`} type="number" min={0} step="any" dir="ltr" className="form-control" value={String(p.weight)} disabled={readOnly} onChange={(e) => patchPillar(p.id, { weight: e.target.value })} /></div>
                        <div className="col-6 col-md-3"><label className="form-label" htmlFor={`p-min-${p.id}`}>الحد الأدنى (اختياري)</label><input id={`p-min-${p.id}`} type="number" min={0} max={100} step="any" dir="ltr" className="form-control" value={p.min_score === null ? "" : String(p.min_score)} disabled={readOnly} onChange={(e) => patchPillar(p.id, { min_score: e.target.value === "" ? null : e.target.value })} /></div>
                    </div>
                ) : <p className="form-text">تُحسب كمحور ضمني. اربطها بمحور من نافذة التعديل.</p>}
                <CriteriaList criteria={crit} readOnly={readOnly} onEdit={setEditing} onMove={moveCriterion(pid)} onDelete={(c) => setRs((r) => ({ ...r, criteria: r.criteria.filter((x) => x.id !== c.id) }))} />
                {!readOnly ? <button type="button" className="btn btn-outline-primary btn-sm mt-3" onClick={() => setEditing(newCriterion(pid))}><i className="bi bi-plus-lg" aria-hidden="true" /> إضافة معيار</button> : null}
            </div>
        );
    };

    const weightSum = pillars.reduce((s, p) => s + (num(p.weight) > 0 ? num(p.weight) : 0), 0);

    return (
        <div className="d-flex flex-column gap-3" data-testid="rule-set-builder">
            <div className="app-page-toolbar mb-0">
                <span className="d-flex flex-wrap align-items-center gap-2">
                    <strong>{rs.name_ar || "مجموعة قواعد"}</strong>
                    <span className="text-muted">v<bdi dir="ltr">{rs.version_number}</bdi></span>
                    <StatusBadge label={rs.status === "draft" ? "مسودة" : rs.status === "published" ? "معتمدة (مجمّدة)" : "مؤرشفة"} tone={rs.status === "draft" ? "warning" : rs.status === "published" ? "success" : "neutral"} />
                </span>
                <span className="d-flex flex-wrap gap-2">
                    <button type="button" className="btn btn-outline-primary" onClick={doExport}><i className="bi bi-download" aria-hidden="true" /> تصدير JSON</button>
                    {!readOnly ? (
                        <>
                            <button type="button" className="btn btn-outline-primary" onClick={() => fileRef.current?.click()}><i className="bi bi-upload" aria-hidden="true" /> استيراد JSON</button>
                            <input ref={fileRef} type="file" accept="application/json,.json" className="d-none" aria-label="ملف القواعد" data-testid="import-input" onChange={(e) => { const f = e.target.files?.[0]; if (f) void doImport(f); e.target.value = ""; }} />
                        </>
                    ) : null}
                    {onDuplicate ? <button type="button" className="btn btn-outline-primary" onClick={() => onDuplicate(rs)}><i className="bi bi-copy" aria-hidden="true" /> نسخ كمسودة جديدة</button> : null}
                    {onSave && !readOnly ? <button type="button" className="btn btn-primary" onClick={() => onSave(rs)}>حفظ المسودة</button> : null}
                </span>
            </div>

            {readOnly ? <div className="alert alert-info mb-0" role="status">هذه النسخة معتمدة ومجمّدة ولا يمكن تعديلها. انسخها كمسودة لإجراء تغييرات.</div> : null}
            {importMsg?.error ? <div className="alert alert-danger mb-0" role="alert">{importMsg.error}</div> : null}
            {importMsg?.issues ? <div className={`alert ${importMsg.issues.length ? "alert-warning" : "alert-success"} mb-0`} role="status" data-testid="import-result">{importMsg.issues.length ? `تم الاستيراد كمسودة مع ${importMsg.issues.length} ملاحظة يجب إصلاحها قبل الاعتماد.` : "تم الاستيراد كمسودة ويمكن اعتمادها."}</div> : null}

            {!readOnly && issues.length > 0 ? (
                <div className="alert alert-warning mb-0" role="status" data-testid="validation">
                    <strong>قبل الاعتماد:</strong>
                    <ul className="mb-0">{issues.map((x, i) => <li key={i}>{x.message}</li>)}</ul>
                </div>
            ) : null}

            <ul className="nav nav-tabs" role="tablist">
                {([["matrix", "المحاور والمعايير"], ["bands", "شرائح النتيجة"], ["preview", "معاينة"]] as const).map(([k, l]) => (
                    <li key={k} className="nav-item" role="presentation">
                        <button type="button" role="tab" aria-selected={tab === k} className={`nav-link app-tab${tab === k ? " active" : ""}`} onClick={() => setTab(k)}>{l}</button>
                    </li>
                ))}
            </ul>

            {tab === "matrix" ? (
                <SectionCard title="المحاور والمعايير">
                    <p className="form-text mt-0">مجموع أوزان المحاور: <bdi dir="ltr">{weightSum}</bdi>. الأوزان نسبية؛ النتيجة من 100.</p>
                    <div className="d-flex flex-column gap-3">
                        {pillars.length === 0 && rs.criteria.length === 0 ? <p className="text-muted mb-0">لا توجد محاور بعد.</p> : null}
                        {pillars.map((p, i) => pillarSection(p, i))}
                        {pillarSection(null, -1)}
                    </div>
                    {!readOnly ? (
                        <div className="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" className="btn btn-primary" onClick={addPillar}><i className="bi bi-plus-lg" aria-hidden="true" /> إضافة محور</button>
                            {pillars.length === 0 ? <button type="button" className="btn btn-outline-primary" onClick={() => setEditing(newCriterion(null))}>إضافة معيار بلا محور</button> : null}
                        </div>
                    ) : null}
                </SectionCard>
            ) : null}
            {tab === "bands" ? (
                <SectionCard title="شرائح النتيجة">
                    <BandsEditor bands={rs.bands} ruleSetId={rs.id} readOnly={readOnly} onChange={(bands: ScoreBand[]) => setRs((r) => ({ ...r, bands }))} />
                </SectionCard>
            ) : null}
            {tab === "preview" ? (
                <SectionCard title="معاينة الحساب">
                    <PreviewPanel ruleSet={rs} fetcher={previewFetcher} />
                </SectionCard>
            ) : null}

            <CriterionDrawer criterion={editing} pillars={pillars} readOnly={readOnly} onClose={() => setEditing(null)} onSave={saveCriterion} />
        </div>
    );
}
