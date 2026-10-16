import type { ScoreBand } from "../../types/readiness";
import { COLOR_TOKENS, colorVar, num, sortByOrder } from "./scoring";

/** Score bands (name + range + #13 colour token). Ranges are entered by Beacon; nothing is preset. */
const FieldError = ({ msg }: { msg?: string }) => (msg ? <div className="invalid-feedback d-block" role="alert">{msg}</div> : null);

/** Unsaved rows have a negative id. Each row is saved on its own (POST/PUT bands), deleted with DELETE. */
export function BandsEditor({ bands, ruleSetId, onChange, readOnly, onSaveRow, onDeleteRow, errors = {}, dirty = () => true }: {
    bands: ScoreBand[]; ruleSetId: number; onChange: (b: ScoreBand[]) => void; readOnly?: boolean;
    onSaveRow?: (b: ScoreBand) => void; onDeleteRow?: (b: ScoreBand) => void;
    /** Server errors per band id. */
    errors?: Record<number, Record<string, string>>;
    dirty?: (b: ScoreBand) => boolean;
}) {
    const list = sortByOrder(bands);
    const set = (i: number, patch: Partial<ScoreBand>) => onChange(list.map((b, k) => (k === i ? { ...b, ...patch } : b)));
    const show = (v: string | number) => (Number.isFinite(num(v)) ? String(v) : "");

    return (
        <div data-testid="bands-editor" className="d-flex flex-column gap-3">
            {list.length === 0 ? <p className="text-muted mb-0">لا توجد شرائح بعد. أضف شرائح تغطي النتيجة من 0 إلى 100.</p> : null}
            {list.map((b, i) => (
                <div key={i} className="app-mobile-record" data-testid="band-row">
                    <div className="row g-2 align-items-end">
                        <div className="col-12 col-md-4">
                            <label className="form-label" htmlFor={`band-label-${i}`}>اسم الشريحة</label>
                            <input id={`band-label-${i}`} className="form-control" value={b.label_ar} disabled={readOnly} onChange={(e) => set(i, { label_ar: e.target.value })} />
                            <FieldError msg={errors[b.id]?.label_ar} />
                        </div>
                        <div className="col-6 col-md-2">
                            <label className="form-label" htmlFor={`band-min-${i}`}>من</label>
                            <input id={`band-min-${i}`} type="number" inputMode="decimal" dir="ltr" className="form-control" value={show(b.min)} disabled={readOnly} onChange={(e) => set(i, { min: e.target.value })} />
                            <FieldError msg={errors[b.id]?.min} />
                        </div>
                        <div className="col-6 col-md-2">
                            <label className="form-label" htmlFor={`band-max-${i}`}>إلى</label>
                            <input id={`band-max-${i}`} type="number" inputMode="decimal" dir="ltr" className="form-control" value={show(b.max)} disabled={readOnly} onChange={(e) => set(i, { max: e.target.value })} />
                            <FieldError msg={errors[b.id]?.max} />
                        </div>
                        <div className="col-9 col-md-3">
                            <span className="form-label d-block" id={`band-color-${i}`}>اللون</span>
                            <div role="radiogroup" aria-labelledby={`band-color-${i}`} className="d-flex flex-wrap gap-2">
                                {COLOR_TOKENS.map((c) => (
                                    <button
                                        key={c.token}
                                        type="button"
                                        role="radio"
                                        aria-checked={b.color_token === c.token}
                                        aria-label={c.label_ar}
                                        title={c.label_ar}
                                        disabled={readOnly}
                                        className={`app-icon-button${b.color_token === c.token ? " active" : ""}`}
                                        onClick={() => set(i, { color_token: c.token })}
                                    >
                                        <i className={`bi ${b.color_token === c.token ? "bi-check-circle-fill" : "bi-circle-fill"}`} style={{ color: colorVar(c.token) }} aria-hidden="true" />
                                    </button>
                                ))}
                            </div>
                        </div>
                        {!readOnly ? (
                            <div className="col-3 col-md-1 d-flex justify-content-end gap-1">
                                {onSaveRow && dirty(b) ? (
                                    <button type="button" className="app-icon-button" aria-label={`حفظ الشريحة ${i + 1}`} onClick={() => onSaveRow(b)}>
                                        <i className="bi bi-check-lg" aria-hidden="true" />
                                    </button>
                                ) : null}
                                <button type="button" className="app-icon-button" aria-label={`حذف الشريحة ${i + 1}`} onClick={() => (onDeleteRow && b.id > 0 ? onDeleteRow(b) : onChange(list.filter((_, k) => k !== i).map((x, k) => ({ ...x, sort_order: k }))))}>
                                    <i className="bi bi-trash" aria-hidden="true" />
                                </button>
                            </div>
                        ) : null}
                    </div>
                    {errors[b.id]?.color_token ? <FieldError msg={errors[b.id].color_token} /> : null}
                    {errors[b.id]?._ ? <FieldError msg={errors[b.id]._} /> : null}
                </div>
            ))}
            {!readOnly ? (
                <div>
                    {/* New band starts empty: ranges and colour are a Beacon decision. */}
                    <button type="button" className="btn btn-outline-primary" onClick={() => onChange([...list, { id: -(Date.now() + list.length), readiness_rule_set_id: ruleSetId, label_ar: "", min: "", max: "", color_token: "muted", sort_order: list.length }])}>
                        <i className="bi bi-plus-lg" aria-hidden="true" /> إضافة شريحة
                    </button>
                </div>
            ) : null}
        </div>
    );
}
