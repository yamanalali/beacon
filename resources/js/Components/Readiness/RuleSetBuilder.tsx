import { useEffect, useRef, useState, type DragEvent } from "react";
import type { Criterion, Pillar, ScoreBand } from "../../types/readiness";
import { SectionCard } from "../Common/SectionCard";
import { StatusBadge } from "../Feedback/StatusBadge";
import { serverActions, type ApiResult, type BuilderActions } from "./adminApi";
import { BandsEditor } from "./BandsEditor";
import { CriterionDrawer } from "./CriterionDrawer";
import { PreviewPanel } from "./PreviewPanel";
import { serverPreview, type PreviewFetcher } from "./previewApi";
import { CRITERION_TYPES, NO_BANDS_MESSAGE, defaultConfig, importRuleSet, num, reorder, resolvePillarWeights, sortByOrder, validateRuleSet } from "./scoring";
import type { RuleSetDraft } from "./types";

type Props = {
    /** From Inertia props (ReadinessAdminController@index, backend #74). Re-synced after every save. */
    ruleSet: RuleSetDraft;
    actions?: BuilderActions;
    previewFetcher?: PreviewFetcher | null;
};

type Notice = { tone: "success" | "danger" | "warning"; text: string; list?: string[] } | null;

const typeLabel = (t: string) => CRITERION_TYPES.find((x) => x.type === t)?.label_ar ?? t;
let localId = -1000;
const nextId = () => --localId;
const FieldError = ({ msg }: { msg?: string }) => (msg ? <div className="invalid-feedback d-block" role="alert">{msg}</div> : null);

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
                        <span className="small text-muted">الوزن <bdi dir="ltr">{String(c.weight)}</bdi> · <bdi dir="ltr">{((num(c.weight) / (criteria.reduce((sum, row) => sum + num(row.weight), 0) || 1)) * 100).toFixed(1)}%</bdi> من المحور</span>
                        {!readOnly ? <MoveButtons index={i} count={criteria.length} onMove={onMove} label={c.label_ar || c.code} /> : null}
                        <button type="button" className="app-icon-button" aria-label={`${readOnly ? "عرض" : "تعديل"} ${c.label_ar || c.code}`} onClick={() => onEdit(c)}><i className={`bi ${readOnly ? "bi-eye" : "bi-pencil"}`} aria-hidden="true" /></button>
                        {!readOnly ? <button type="button" className="app-icon-button" aria-label={`حذف ${c.label_ar || c.code}`} onClick={() => onDelete(c)}><i className="bi bi-trash" aria-hidden="true" /></button> : null}
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function RuleSetBuilder({ ruleSet, actions = serverActions, previewFetcher = serverPreview }: Props) {
    const [rs, setRs] = useState<RuleSetDraft>(ruleSet);
    const [editing, setEditing] = useState<Criterion | null>(null);
    const [drawerErrors, setDrawerErrors] = useState<Record<string, string>>({});
    const [rowErrors, setRowErrors] = useState<Record<string, Record<string, string>>>({});
    const [busy, setBusy] = useState(false);
    const [tab, setTab] = useState<"matrix" | "bands" | "preview">("matrix");
    const [notice, setNotice] = useState<Notice>(null);
    const [library, setLibrary] = useState<(Criterion & { rule_set?: { name_ar: string; version_number: number } })[] | null>(null);
    const [librarySearch, setLibrarySearch] = useState("");
    const [libraryError, setLibraryError] = useState("");
    const loadLibrary = async () => {
        setLibraryError("");
        try {
            const response = await fetch("/admin/readiness/criteria-library", { headers: { Accept: "application/json" }, credentials: "same-origin" });
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (!Array.isArray(data.criteria)) throw new Error();
            setLibrary(data.criteria);
        } catch { setLibraryError("تعذر تحميل مكتبة الأسئلة. حاول مرة أخرى."); }
    };
    const fileRef = useRef<HTMLInputElement>(null);
    useEffect(() => setRs(ruleSet), [ruleSet]);

    const readOnly = rs.status !== "draft";
    const issues = validateRuleSet(rs);
    const noBands = rs.bands.filter((b) => b.id > 0).length === 0;
    const pillars = sortByOrder(rs.pillars);
    const serverPillar = (id: number) => ruleSet.pillars.find((p) => p.id === id);
    const serverBand = (id: number) => ruleSet.bands.find((b) => b.id === id);

    /** Runs an action; field errors go to `key`, everything else to the notice. */
    const run = async (p: Promise<ApiResult>, key?: string, okText?: string): Promise<boolean> => {
        setBusy(true);
        const r = await p;
        setBusy(false);
        if (r.ok) {
            if (key) setRowErrors((e) => { const n = { ...e }; delete n[key]; return n; });
            setNotice(okText ? { tone: "success", text: okText } : null);
            return true;
        }
        if (key && Object.keys(r.errors).length) setRowErrors((e) => ({ ...e, [key]: r.errors }));
        else setNotice({ tone: "danger", text: r.message, list: Object.values(r.errors) });
        return false;
    };

    // ---- pillars ----
    const patchPillar = (id: number, patch: Partial<Pillar>) => setRs((r) => ({ ...r, pillars: r.pillars.map((p) => (p.id === id ? { ...p, ...patch } : p)) }));
    const pillarDirty = (p: Pillar) => { const s = serverPillar(p.id); return !s || s.code !== p.code || s.label_ar !== p.label_ar || String(s.weight ?? "") !== String(p.weight ?? "") || String(s.min_score ?? "") !== String(p.min_score ?? ""); };
    const savePillar = (p: Pillar) => { if (pillarDirty(p)) void run(actions.savePillar(rs, p), `pillar:${p.id}`); };
    const movePillar = (from: number, to: number) => {
        const next = reorder(rs.pillars, from, to);
        setRs((r) => ({ ...r, pillars: next }));
        const ids = next.map((p) => p.id).filter((id) => id > 0);
        if (ids.length) void run(actions.reorderPillars(rs, ids));
    };
    const dragPillar = useDragList(movePillar);
    // Manager decision: a new pillar starts without a weight = equal share until the admin sets one.
    const addPillar = () => setRs((r) => ({ ...r, pillars: [...r.pillars, { id: nextId(), readiness_rule_set_id: r.id, code: "", label_ar: "", weight: "", min_score: null, sort_order: r.pillars.length }] }));
    const removePillar = (p: Pillar) => {
        if (p.id < 0) setRs((r) => ({ ...r, pillars: r.pillars.filter((x) => x.id !== p.id) }));
        else void run(actions.deletePillar(p));
    };

    // ---- criteria ----
    const inPillar = (pid: number | null) => sortByOrder(rs.criteria.filter((c) => (pid === null ? c.readiness_pillar_id === null || !rs.pillars.some((p) => p.id === c.readiness_pillar_id) : c.readiness_pillar_id === pid)));
    const moveCriterion = (pid: number | null) => (from: number, to: number) => {
        const moved = new Map(reorder(inPillar(pid), from, to).map((c) => [c.id, c]));
        const merged = rs.criteria.map((c) => moved.get(c.id) ?? c);
        setRs((r) => ({ ...r, criteria: merged }));
        // The server wants every criterion id of the set, in display order (pillar order, then implicit).
        const order = [...pillars.map((p) => p.id), null].flatMap((id) => sortByOrder(merged.filter((c) => (id === null ? !rs.pillars.some((p) => p.id === c.readiness_pillar_id) : c.readiness_pillar_id === id))));
        void run(actions.reorderCriteria(rs, order.map((c) => c.id)));
    };
    const saveCriterion = async (c: Criterion) => {
        setBusy(true);
        const r = await actions.saveCriterion(rs, c);
        setBusy(false);
        if (r.ok) { setEditing(null); setDrawerErrors({}); setNotice(null); return; }
        setDrawerErrors(Object.keys(r.errors).length ? r.errors : { _: r.message });
    };
    const newCriterion = (pid: number | null): Criterion => ({
        id: nextId(), readiness_rule_set_id: rs.id, readiness_pillar_id: pid, code: `criterion_${crypto.randomUUID().replaceAll("-", "").slice(0, 16)}`, label_ar: "", description_ar: null,
        // Criterion weight has no default (server requires > 0).
        // TODO: BUSINESS DECISION REQUIRED - default missing-data treatment for new criteria (placeholder: needs_review, the most conservative).
        weight: "1", is_mandatory: false, missing_data_treatment: "needs_review", compliance_requirement_id: null, sort_order: 0, ...defaultConfig("boolean"),
    }) as Criterion;
    const openEditor = (c: Criterion | null) => { setDrawerErrors({}); setEditing(c); };

    // ---- bands ----
    const bandDirty = (b: ScoreBand) => { const s = serverBand(b.id); return !s || s.label_ar !== b.label_ar || String(s.min) !== String(b.min) || String(s.max) !== String(b.max) || s.color_token !== b.color_token; };
    const bandErrors: Record<number, Record<string, string>> = {};
    for (const b of rs.bands) if (rowErrors[`band:${b.id}`]) bandErrors[b.id] = rowErrors[`band:${b.id}`];

    // ---- set level ----
    const doImport = async (file: File) => {
        if (file.size > 2 * 1024 * 1024) return setNotice({ tone: "danger", text: "حجم الملف أكبر من المسموح (2 ميغابايت)." });
        let contents: string;
        try { contents = await file.text(); }
        catch { return setNotice({ tone: "danger", text: "تعذرت قراءة الملف. اختر الملف مرة أخرى." }); }
        const parsed = importRuleSet(contents, { id: rs.id, version_number: rs.version_number });
        if (!parsed.ok) return setNotice({ tone: "danger", text: parsed.error });
        if (parsed.issues.length) return setNotice({ tone: "warning", text: "الملف لا يجتاز فحص القواعد، أصلحه ثم أعد الاستيراد:", list: parsed.issues.map((i) => i.message) });
        await run(actions.importFile(file), undefined, "تم استيراد القواعد كمسودة جديدة.");
    };
    const publishBlocked = readOnly || issues.length > 0 || noBands || rs.pillars.some((p) => p.id < 0) || rs.bands.some((b) => b.id < 0);
    const publish = () => { if (!publishBlocked) void run(actions.publish(rs), undefined, "تم اعتماد القواعد ونشرها."); };

    const pillarSection = (p: Pillar | null, i: number) => {
        const pid = p?.id ?? null;
        const crit = p && p.id < 0 ? [] : inPillar(pid);
        if (!p && crit.length === 0) return null;
        const e = p ? rowErrors[`pillar:${p.id}`] ?? {} : {};
        return (
            <div key={pid ?? "implicit"} className="app-mobile-record" data-testid="pillar-card" {...(p && p.id > 0 && !readOnly ? dragPillar(i) : {})}>
                <div className="fw-semibold d-flex flex-wrap align-items-center gap-2">
                    {p && !readOnly ? <i className="bi bi-grip-vertical text-muted" aria-hidden="true" style={{ cursor: "grab" }} /> : null}
                    <span className="flex-grow-1">{p ? p.label_ar || p.code || "محور جديد" : "معايير غير مصنفة"}</span>
                    {p && p.id > 0 && !readOnly ? <MoveButtons index={i} count={pillars.length} onMove={movePillar} label={p.label_ar || "المحور"} /> : null}
                    {p && !readOnly ? <button type="button" className="app-icon-button" aria-label={`حذف المحور ${p.label_ar || p.code}`} onClick={() => removePillar(p)}><i className="bi bi-trash" aria-hidden="true" /></button> : null}
                </div>
                {p ? (
                    <div className="row g-2 mb-3" onBlur={(ev) => { if (p.id > 0 && !ev.currentTarget.contains(ev.relatedTarget as Node | null)) savePillar(rs.pillars.find((x) => x.id === p.id) ?? p); }}>
                        <div className="col-6 col-md-3"><label className="form-label" htmlFor={`p-code-${p.id}`}>الرمز</label><input id={`p-code-${p.id}`} dir="ltr" className="form-control" value={p.code} disabled={readOnly} onChange={(ev) => patchPillar(p.id, { code: ev.target.value })} /><FieldError msg={e.code} /></div>
                        <div className="col-6 col-md-4"><label className="form-label" htmlFor={`p-label-${p.id}`}>اسم المحور</label><input id={`p-label-${p.id}`} className="form-control" value={p.label_ar} disabled={readOnly} onChange={(ev) => patchPillar(p.id, { label_ar: ev.target.value })} /><FieldError msg={e.label_ar} /></div>
                        <div className="col-6 col-md-2"><label className="form-label" htmlFor={`p-weight-${p.id}`}>الوزن</label><input id={`p-weight-${p.id}`} type="number" min={0} step="any" dir="ltr" className="form-control" placeholder="متساوٍ" value={p.weight === null ? "" : String(p.weight)} disabled={readOnly} onChange={(ev) => patchPillar(p.id, { weight: ev.target.value })} /><FieldError msg={e.weight} /></div>
                        <div className="col-6 col-md-3"><label className="form-label" htmlFor={`p-min-${p.id}`}>الحد الأدنى (اختياري)</label><input id={`p-min-${p.id}`} type="number" min={0} max={100} step="any" dir="ltr" className="form-control" value={p.min_score === null ? "" : String(p.min_score)} disabled={readOnly} onChange={(ev) => patchPillar(p.id, { min_score: ev.target.value === "" ? null : ev.target.value })} /><FieldError msg={e.min_score} /></div>
                        {p.id < 0 ? <div className="col-12"><button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => void run(actions.savePillar(rs, p), `pillar:${p.id}`)}>حفظ المحور</button></div> : null}
                    </div>
                ) : <p className="form-text">تُحسب كمحور ضمني بوزن متساوٍ. اربطها بمحور من نافذة التعديل.</p>}
                {p && p.id < 0 ? null : (
                    <>
                        <CriteriaList criteria={crit} readOnly={readOnly} onEdit={openEditor} onMove={moveCriterion(pid)} onDelete={(c) => void run(actions.deleteCriterion(c))} />
                        {!readOnly ? <button type="button" className="btn btn-outline-primary btn-sm mt-3" onClick={() => openEditor(newCriterion(pid))}><i className="bi bi-plus-lg" aria-hidden="true" /> إضافة معيار</button> : null}
                    </>
                )}
            </div>
        );
    };

    const effective = resolvePillarWeights(pillars.map((p) => (Number.isFinite(num(p.weight)) ? num(p.weight) : null)));

    return (
        <div className="d-flex flex-column gap-3" data-testid="rule-set-builder">
            <div className="app-page-toolbar mb-0">
                <span className="d-flex flex-wrap align-items-center gap-2">
                    <strong>{rs.name_ar || "مجموعة قواعد"}</strong>
                    <span className="text-muted">v<bdi dir="ltr">{rs.version_number}</bdi></span>
                    <StatusBadge label={rs.status === "draft" ? "مسودة" : rs.status === "published" ? "معتمدة (مجمّدة)" : "مؤرشفة"} tone={rs.status === "draft" ? "warning" : rs.status === "published" ? "success" : "neutral"} />
                </span>
                <span className="d-flex flex-wrap gap-2">
                    <a className="btn btn-outline-primary" href={actions.exportUrl(rs)} download><i className="bi bi-download" aria-hidden="true" /> تنزيل ملف القواعد</a>
                    <button type="button" className="btn btn-outline-primary" disabled={busy} onClick={() => fileRef.current?.click()}><i className="bi bi-upload" aria-hidden="true" /> تحميل ملف قواعد</button>
                    <input ref={fileRef} type="file" accept="application/json,.json" className="d-none" aria-label="ملف القواعد" data-testid="import-input" onChange={(e) => { const f = e.target.files?.[0]; if (f) void doImport(f); e.target.value = ""; }} />
                    <button type="button" className="btn btn-outline-primary" disabled={busy} onClick={() => void run(actions.duplicate(rs), undefined, "تم إنشاء مسودة جديدة من هذه النسخة.")}><i className="bi bi-copy" aria-hidden="true" /> نسخ كمسودة جديدة</button>
                    {!readOnly ? <button type="button" className="btn btn-primary" disabled={publishBlocked || busy} title={noBands ? NO_BANDS_MESSAGE : undefined} onClick={publish}>اعتماد ونشر</button> : null}
                </span>
            </div>

            {readOnly ? <div className="alert alert-info mb-0" role="status">هذه النسخة {rs.status === "published" ? "معتمدة" : "مؤرشفة"} ومجمّدة ولا يمكن تعديلها. انسخها كمسودة لإجراء تغييرات.</div> : null}
            {notice ? (
                <div className={`alert alert-${notice.tone} mb-0`} role={notice.tone === "success" ? "status" : "alert"} data-testid="builder-notice">
                    {notice.text}
                    {notice.list?.length ? <ul className="mb-0">{notice.list.map((m, i) => <li key={i}>{m}</li>)}</ul> : null}
                </div>
            ) : null}
            {!readOnly && (issues.length > 0 || noBands) ? (
                <div className="alert alert-warning mb-0" role="status" data-testid="validation">
                    <strong>قبل الاعتماد:</strong>
                    <ul className="mb-0">{[...new Set([...issues.map((x) => x.message), ...(noBands ? [NO_BANDS_MESSAGE] : [])])].map((m, i) => <li key={i}>{m}</li>)}</ul>
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
                    <p className="alert alert-info">1. حدد المحاور وأهميتها. 2. أضف الأسئلة وحدد نقاط الإجابات. 3. جرّب المعاينة وراجع الشرائح قبل النشر.</p>
                    <p className="form-text mt-0">المحور بلا وزن يأخذ حصة متساوية (متوسط الأوزان المحددة، أو 1 إن لم يُحدد أي وزن). الأوزان الفعلية: <bdi dir="ltr">{effective.map((w) => Math.round(w * 100) / 100).join(" / ") || "-"}</bdi></p>
                    <div className="d-flex flex-column gap-3">
                        {pillars.length === 0 && rs.criteria.length === 0 ? <p className="text-muted mb-0">لا توجد محاور بعد.</p> : null}
                        {pillars.map((p, i) => pillarSection(p, i))}
                        {pillarSection(null, -1)}
                    </div>
                    {!readOnly ? (
                        <div className="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" className="btn btn-primary" onClick={addPillar}><i className="bi bi-plus-lg" aria-hidden="true" /> إضافة محور</button>
                            <button type="button" className="btn btn-outline-primary" onClick={() => openEditor(newCriterion(null))}>إضافة معيار بلا محور</button>
                        </div>
                    ) : null}
                </SectionCard>
            ) : null}
            {tab === "bands" ? (
                <SectionCard title="شرائح النتيجة">
                    <BandsEditor bands={rs.bands} ruleSetId={rs.id} readOnly={readOnly} errors={bandErrors} dirty={bandDirty}
                        onChange={(bands: ScoreBand[]) => setRs((r) => ({ ...r, bands }))}
                        onSaveRow={(b) => void run(actions.saveBand(rs, b), `band:${b.id}`)}
                        onDeleteRow={(b) => void run(actions.deleteBand(b))} />
                </SectionCard>
            ) : null}
            {tab === "preview" ? (
                <SectionCard title="معاينة الحساب">
                    <PreviewPanel ruleSet={ruleSet} fetcher={previewFetcher} />
                </SectionCard>
            ) : null}

            {!readOnly ? <SectionCard title="إعادة استخدام سؤال">
                <p className="form-text">انسخ سؤالاً من آخر 200 معيار محفوظ. راجع الوزن والمحور والإجابات قبل الحفظ؛ يبقى السؤال الأصلي محفوظاً.</p>
                <button type="button" className="btn btn-outline-primary" onClick={() => void loadLibrary()}>تحميل مكتبة الأسئلة</button>
                {libraryError ? <p role="alert">{libraryError}</p> : null}
                {library ? <><input className="form-control my-2" aria-label="بحث في الأسئلة" placeholder="ابحث باسم السؤال" value={librarySearch} onChange={(e) => setLibrarySearch(e.target.value)} />
                    <div style={{ maxHeight: 320, overflowY: "auto" }}>
                        {library.filter((c) => c.label_ar.includes(librarySearch)).map((c) => <div key={c.id} className="d-flex gap-2 justify-content-between border-bottom py-2"><span>{c.label_ar} <small className="text-muted">{c.rule_set?.name_ar} v{c.rule_set?.version_number}</small></span><button type="button" className="btn btn-sm btn-outline-primary" onClick={() => { const fresh = newCriterion(null); openEditor({ ...structuredClone(c), id: fresh.id, code: fresh.code, readiness_rule_set_id: rs.id, readiness_pillar_id: null, compliance_requirement_id: null, sort_order: 0 }); }}>استخدام السؤال</button></div>)}
                        {library.length === 0 ? <p>لا توجد أسئلة محفوظة بعد.</p> : null}
                    </div></> : null}
            </SectionCard> : null}
            <CriterionDrawer criterion={editing} pillars={pillars} readOnly={readOnly} errors={drawerErrors} saving={busy} onClose={() => openEditor(null)} onSave={(c) => void saveCriterion(c)} />
        </div>
    );
}
