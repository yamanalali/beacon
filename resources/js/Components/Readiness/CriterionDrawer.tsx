import { useEffect, useId, useState } from "react";
import { useT } from "../../Hooks/useT";
import type { ChoiceOption, Criterion, CriterionType, MissingDataTreatment, NumericBand, Pillar } from "../../types/readiness";
import { CRITERION_TYPES, TREATMENT_LABELS, defaultConfig, num } from "./scoring";

const show = (n: string | number | null | undefined) => (n === null || n === undefined || n === "" || Number.isNaN(Number(n)) ? "" : String(n));

function NumericBandsFields({ c, set, readOnly }: { c: Extract<Criterion, { type: "numeric_bands" }>; set: (c: Criterion) => void; readOnly?: boolean }) {
    const tr = useT();
    const bands = c.config.bands;
    const put = (bs: NumericBand[]) => set({ ...c, config: { ...c.config, bands: bs } });
    return (
        <fieldset className="d-flex flex-column gap-2">
            <legend className="form-label fw-semibold">{tr("readiness.criterion.numeric_bands", undefined, "الشرائح الرقمية")}</legend>
            <label className="form-label" htmlFor="cfg-unit">{tr("readiness.criterion.unit", undefined, "الوحدة")}</label>
            <input id="cfg-unit" className="form-control" value={c.config.unit} disabled={readOnly} onChange={(e) => set({ ...c, config: { ...c.config, unit: e.target.value } })} />
            {bands.map((b, i) => (
                <div key={i} className="row g-2 align-items-end" data-testid="numeric-band">
                    <div className="col-4"><label className="form-label" htmlFor={`nb-min-${i}`}>{tr("common.from", undefined, "من")}</label><input id={`nb-min-${i}`} type="number" dir="ltr" className="form-control" value={show(b.min)} disabled={readOnly} placeholder={tr("readiness.criterion.no_limit", undefined, "بلا حد")} onChange={(e) => put(bands.map((x, k) => (k === i ? { ...x, min: e.target.value === "" ? null : num(e.target.value) } : x)))} /></div>
                    <div className="col-4"><label className="form-label" htmlFor={`nb-max-${i}`}>{tr("common.to", undefined, "إلى")}</label><input id={`nb-max-${i}`} type="number" dir="ltr" className="form-control" value={show(b.max)} disabled={readOnly} placeholder={tr("readiness.criterion.no_limit", undefined, "بلا حد")} onChange={(e) => put(bands.map((x, k) => (k === i ? { ...x, max: e.target.value === "" ? null : num(e.target.value) } : x)))} /></div>
                    <div className="col-3"><label className="form-label" htmlFor={`nb-pts-${i}`}>{tr("readiness.criterion.points_01", undefined, "النقاط (0-1)")}</label><input id={`nb-pts-${i}`} type="number" step="0.05" min={0} max={1} dir="ltr" className="form-control" value={show(b.points)} disabled={readOnly} onChange={(e) => put(bands.map((x, k) => (k === i ? { ...x, points: num(e.target.value) } : x)))} /></div>
                    {!readOnly ? <div className="col-1"><button type="button" className="app-icon-button" aria-label={tr("readiness.criterion.delete_band", { n: i + 1 }, `حذف الشريحة ${i + 1}`)} onClick={() => put(bands.filter((_, k) => k !== i))}><i className="bi bi-x-lg" aria-hidden="true" /></button></div> : null}
                </div>
            ))}
            {!readOnly ? <div><button type="button" className="btn btn-outline-primary btn-sm" onClick={() => put([...bands, { min: null, max: null, points: Number.NaN }])}>{tr("readiness.bands.add", undefined, "إضافة شريحة")}</button></div> : null}
        </fieldset>
    );
}

function ChoiceFields({ c, set, readOnly }: { c: Extract<Criterion, { type: "choice" }>; set: (c: Criterion) => void; readOnly?: boolean }) {
    const tr = useT();
    const opts = c.config.options;
    const put = (os: ChoiceOption[]) => set({ ...c, config: { options: os } });
    return (
        <fieldset className="d-flex flex-column gap-2">
            <legend className="form-label fw-semibold">{tr("readiness.criterion.choices_points", undefined, "الخيارات والنقاط")}</legend>
            {opts.map((o, i) => (
                <div key={i} className="row g-2 align-items-end" data-testid="choice-option">
                    <div className="col-4"><label className="form-label" htmlFor={`op-v-${i}`}>{tr("readiness.criterion.value", undefined, "القيمة")}</label><input id={`op-v-${i}`} dir="ltr" className="form-control" value={o.value} disabled={readOnly} onChange={(e) => put(opts.map((x, k) => (k === i ? { ...x, value: e.target.value } : x)))} /></div>
                    <div className="col-4"><label className="form-label" htmlFor={`op-l-${i}`}>{tr("readiness.criterion.label", undefined, "النص")}</label><input id={`op-l-${i}`} className="form-control" value={o.label_ar} disabled={readOnly} onChange={(e) => put(opts.map((x, k) => (k === i ? { ...x, label_ar: e.target.value } : x)))} /></div>
                    <div className="col-3"><label className="form-label" htmlFor={`op-p-${i}`}>{tr("readiness.criterion.points_01", undefined, "النقاط (0-1)")}</label><input id={`op-p-${i}`} type="number" step="0.05" min={0} max={1} dir="ltr" className="form-control" value={show(o.points)} disabled={readOnly} onChange={(e) => put(opts.map((x, k) => (k === i ? { ...x, points: num(e.target.value) } : x)))} /></div>
                    {!readOnly ? <div className="col-1"><button type="button" className="app-icon-button" aria-label={tr("readiness.criterion.delete_option", { n: i + 1 }, `حذف الخيار ${i + 1}`)} onClick={() => put(opts.filter((_, k) => k !== i))}><i className="bi bi-x-lg" aria-hidden="true" /></button></div> : null}
                </div>
            ))}
            {!readOnly ? <div><button type="button" className="btn btn-outline-primary btn-sm" onClick={() => put([...opts, { value: "", label_ar: "", points: Number.NaN }])}>{tr("readiness.criterion.add_option", undefined, "إضافة خيار")}</button></div> : null}
        </fieldset>
    );
}

function ConfigFields({ c, set, readOnly }: { c: Criterion; set: (c: Criterion) => void; readOnly?: boolean }) {
    const tr = useT();
    switch (c.type) {
        case "boolean":
            return <p className="text-muted mb-0">{tr("readiness.criterion.boolean_hint", undefined, "نعم = نقطة كاملة، لا = صفر.")}</p>;
        case "numeric_bands":
            return <NumericBandsFields c={c} set={set} readOnly={readOnly} />;
        case "choice":
            return <ChoiceFields c={c} set={set} readOnly={readOnly} />;
        case "target_ratio":
            return (
                <div className="row g-2">
                    <div className="col-6"><label className="form-label" htmlFor="cfg-target">{tr("readiness.preview.target", undefined, "الهدف")}</label><input id="cfg-target" type="number" dir="ltr" className="form-control" value={c.config.target > 0 ? c.config.target : ""} disabled={readOnly} onChange={(e) => set({ ...c, config: { ...c.config, target: num(e.target.value) } })} /></div>
                    <div className="col-6"><label className="form-label" htmlFor="cfg-dir">{tr("readiness.criterion.direction", undefined, "الاتجاه")}</label>
                        <select id="cfg-dir" className="form-select" value={c.config.direction} disabled={readOnly} onChange={(e) => set({ ...c, config: { ...c.config, direction: e.target.value as "higher" | "lower" } })}>
                            <option value="higher">{tr("readiness.criterion.higher_better", undefined, "الأعلى أفضل")}</option>
                            <option value="lower">{tr("readiness.criterion.lower_better", undefined, "الأقل أفضل")}</option>
                        </select>
                    </div>
                    <p className="form-text mb-0">{tr("readiness.criterion.target_ratio_hint", undefined, "النقاط = النسبة من الهدف، بحد أقصى نقطة كاملة.")}</p>
                </div>
            );
        case "manual_scale":
            return (
                <fieldset className="d-flex flex-column gap-2">
                    <legend className="form-label fw-semibold">{tr("readiness.criterion.scale_labels", undefined, "أوصاف الدرجات (اختياري)")}</legend>
                    {[1, 2, 3, 4, 5].map((n) => (
                        <div key={n} className="input-group">
                            <span className="input-group-text" dir="ltr">{n}</span>
                            <input aria-label={tr("readiness.criterion.grade_desc", { n }, `وصف الدرجة ${n}`)} className="form-control" value={c.config.labels_ar?.[String(n)] ?? ""} disabled={readOnly} onChange={(e) => set({ ...c, config: { ...c.config, labels_ar: { ...(c.config.labels_ar ?? {}), [String(n)]: e.target.value } } })} />
                        </div>
                    ))}
                </fieldset>
            );
    }
}

/** Side drawer (start side in RTL = right on desktop, full width on mobile) to edit one criterion. */
const FieldError = ({ msg }: { msg?: string }) => (msg ? <div className="invalid-feedback d-block" role="alert">{msg}</div> : null);

export function CriterionDrawer({ criterion, pillars, onSave, onClose, readOnly, errors = {}, saving }: {
    criterion: Criterion | null; pillars: Pillar[]; onSave: (c: Criterion) => void; onClose: () => void; readOnly?: boolean;
    /** Server validation errors (422) keyed by field, e.g. "code", "weight", "config.bands". */
    errors?: Record<string, string>; saving?: boolean;
}) {
    const tr = useT();
    const configErrors = Object.entries(errors).filter(([k]) => k === "_" || k === "config" || k.startsWith("config.")).map(([, v]) => v);
    const [c, setC] = useState<Criterion | null>(criterion);
    const titleId = useId();
    const [advanced, setAdvanced] = useState(false);
    useEffect(() => { if (errors.code || errors.missing_data_treatment) setAdvanced(true); }, [errors]);
    useEffect(() => setC(criterion), [criterion]);
    useEffect(() => {
        if (!criterion) return;
        const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [criterion, onClose]);
    if (!criterion || !c) return null;

    const changeType = (type: CriterionType) => setC({ ...c, ...defaultConfig(type) } as Criterion);

    return (
        <>
            <div className="offcanvas-backdrop fade show" onClick={onClose} />
            <aside className="offcanvas offcanvas-start show" style={{ visibility: "visible" }} role="dialog" aria-modal="true" aria-labelledby={titleId} data-testid="criterion-drawer">
                <div className="offcanvas-header border-bottom">
                    <h2 className="offcanvas-title h5" id={titleId}>{readOnly ? tr("readiness.criterion.view_title", undefined, "عرض المعيار") : tr("readiness.criterion.edit_title", undefined, "تعديل المعيار")}</h2>
                    <button type="button" className="btn-close" aria-label={tr("common.close", undefined, "إغلاق")} onClick={onClose} />
                </div>
                <div className="offcanvas-body">
                    <form className="d-flex flex-column gap-3" onSubmit={(e) => { e.preventDefault(); onSave(c); }}>

                        <div><label className="form-label" htmlFor="cr-label">{tr("readiness.criterion.name", undefined, "الاسم")}</label><input id="cr-label" className="form-control" value={c.label_ar} disabled={readOnly} onChange={(e) => setC({ ...c, label_ar: e.target.value })} /><FieldError msg={errors.label_ar} /></div>
                        <div><label className="form-label" htmlFor="cr-description">{tr("readiness.criterion.question_help", undefined, "شرح السؤال (اختياري)")}</label><textarea id="cr-description" className="form-control" value={c.description_ar ?? ""} disabled={readOnly} onChange={(e) => setC({ ...c, description_ar: e.target.value })} /><FieldError msg={errors.description_ar} /></div>
                        <p className="form-text">{tr("readiness.criterion.weight_help", undefined, "الوزن يحدد أهمية السؤال مقارنة ببقية أسئلة المحور؛ لا يلزم أن يكون المجموع 100. وزن 2 يعني ضعف تأثير وزن 1.")}</p>
                        <div className="row g-2">
                            <div className="col-6">
                                <label className="form-label" htmlFor="cr-pillar">{tr("readiness.criterion.pillar", undefined, "المحور")}</label>
                                <select id="cr-pillar" className="form-select" value={c.readiness_pillar_id ?? ""} disabled={readOnly} onChange={(e) => setC({ ...c, readiness_pillar_id: e.target.value === "" ? null : Number(e.target.value) })}>
                                    <option value="">{tr("readiness.result.unclassified", undefined, "غير مصنف")}</option>
                                    {pillars.filter((p) => p.id > 0).map((p) => <option key={p.id} value={p.id}>{p.label_ar || p.code}</option>)}
                                </select>
                                <FieldError msg={errors.readiness_pillar_id} />
                            </div>
                            <div className="col-6"><label className="form-label" htmlFor="cr-weight">{tr("readiness.criterion.pillar_weight", undefined, "الوزن داخل المحور")}</label><input id="cr-weight" type="number" min={0} step="any" dir="ltr" className="form-control" value={show(c.weight)} disabled={readOnly} onChange={(e) => setC({ ...c, weight: e.target.value })} /><FieldError msg={errors.weight} /></div>
                        </div>
                        <div>
                            <label className="form-label" htmlFor="cr-type">{tr("readiness.criterion.type", undefined, "نوع المعيار")}</label>
                            <select id="cr-type" className="form-select" value={c.type} disabled={readOnly} onChange={(e) => changeType(e.target.value as CriterionType)}>
                                {CRITERION_TYPES.map((ct) => <option key={ct.type} value={ct.type}>{ct.label_ar}</option>)}
                            </select>
                        </div>
                        <ConfigFields c={c} set={setC} readOnly={readOnly} />
                        {configErrors.length ? <div className="alert alert-danger py-2 small mb-0" role="alert">{configErrors.map((m, i) => <div key={i}>{m}</div>)}</div> : null}
                        <details open={advanced} onToggle={(e) => setAdvanced(e.currentTarget.open)}><summary>{tr("readiness.criterion.advanced", undefined, "إعدادات متقدمة")}</summary>
                        <div><label className="form-label" htmlFor="cr-code">{tr("readiness.criterion.code", undefined, "الرمز")}</label><input id="cr-code" dir="ltr" className="form-control" value={c.code} disabled={readOnly} onChange={(e) => setC({ ...c, code: e.target.value })} /><FieldError msg={errors.code} /></div>
                        <div>
                            <label className="form-label" htmlFor="cr-missing">{tr("readiness.criterion.missing_data", undefined, "عند غياب البيانات")}</label>
                            <select id="cr-missing" className="form-select" value={c.missing_data_treatment} disabled={readOnly} onChange={(e) => setC({ ...c, missing_data_treatment: e.target.value as MissingDataTreatment })}>
                                {Object.entries(TREATMENT_LABELS).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </select>
                        </div>
                        </details>
                        <div className="form-check">
                            <input id="cr-mandatory" type="checkbox" className="form-check-input" checked={c.is_mandatory} disabled={readOnly} onChange={(e) => setC({ ...c, is_mandatory: e.target.checked })} />
                            <label className="form-check-label" htmlFor="cr-mandatory">{tr("readiness.criterion.mandatory_checkbox", undefined, "شرط إلزامي (يمنع النتيجة إن لم يُستوفَ)")}</label>
                        </div>
                        <div className="d-flex gap-2 justify-content-end border-top pt-3">
                            <button type="button" className="btn btn-outline-primary" onClick={onClose}>{readOnly ? tr("common.close", undefined, "إغلاق") : tr("common.cancel", undefined, "إلغاء")}</button>
                            {!readOnly ? <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? tr("readiness.criterion.saving", undefined, "جارٍ الحفظ…") : tr("readiness.criterion.save", undefined, "حفظ المعيار")}</button> : null}
                        </div>
                    </form>
                </div>
            </aside>
        </>
    );
}
