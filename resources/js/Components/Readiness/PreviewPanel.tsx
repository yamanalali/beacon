import { useEffect, useState } from "react";
import type { Criterion } from "../../types/readiness";
import type { Answers, AnswerValue, RuleSetDraft as RuleSet } from "./types";
import { AssessmentResult } from "./AssessmentResult";
import { runPreview, serverPreview, type PreviewFetcher, type PreviewResult } from "./previewApi";
import { sortByOrder } from "./scoring";

function AnswerInput({ c, value, onChange }: { c: Criterion; value: AnswerValue; onChange: (v: AnswerValue) => void }) {
    const id = `ans-${c.code}`;
    const label = <label className="form-label" htmlFor={id}>{c.label_ar || c.code}{c.is_mandatory ? " *" : ""}</label>;
    const str = value === null || value === undefined ? "" : String(value);
    switch (c.type) {
        case "boolean":
            return (
                <div>{label}
                    <select id={id} className="form-select" value={str} onChange={(e) => onChange(e.target.value === "" ? null : e.target.value === "true")}>
                        <option value="">بيانات غير متوفرة</option><option value="true">نعم</option><option value="false">لا</option>
                    </select>
                </div>
            );
        case "choice":
            return (
                <div>{label}
                    <select id={id} className="form-select" value={str} onChange={(e) => onChange(e.target.value === "" ? null : e.target.value)}>
                        <option value="">بيانات غير متوفرة</option>
                        {c.config.options.map((o) => <option key={o.value} value={o.value}>{o.label_ar || o.value}</option>)}
                    </select>
                </div>
            );
        case "manual_scale":
            return (
                <div>{label}
                    <select id={id} className="form-select" value={str} onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))}>
                        <option value="">بيانات غير متوفرة</option>
                        {Array.from({ length: Math.max(0, c.config.max - c.config.min + 1) }, (_, i) => c.config.min + i).map((n) => <option key={n} value={n}>{n}{c.config.labels_ar?.[String(n)] ? ` - ${c.config.labels_ar[String(n)]}` : ""}</option>)}
                    </select>
                </div>
            );
        default:
            return (
                <div>{label}
                    <div className="input-group">
                        <input id={id} type="number" step="any" dir="ltr" className="form-control" placeholder="بيانات غير متوفرة" value={str} onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))} />
                        {c.type === "numeric_bands" && c.config.unit ? <span className="input-group-text">{c.config.unit}</span> : null}
                        {c.type === "target_ratio" && c.config.target > 0 ? <span className="input-group-text">الهدف <bdi dir="ltr">&nbsp;{c.config.target}</bdi></span> : null}
                    </div>
                </div>
            );
    }
}

/** Test answers -> server preview (client mirror as fallback while the endpoint does not exist). */
export function PreviewPanel({ ruleSet, fetcher = serverPreview, delay = 300 }: { ruleSet: RuleSet; fetcher?: PreviewFetcher | null; delay?: number }) {
    const [answers, setAnswers] = useState<Answers>({});
    const [result, setResult] = useState<PreviewResult | null>(null);

    useEffect(() => {
        let alive = true;
        const t = setTimeout(() => { void runPreview(ruleSet, answers, fetcher).then((r) => alive && setResult(r)); }, delay);
        return () => { alive = false; clearTimeout(t); };
    }, [ruleSet, answers, fetcher, delay]);

    const criteria = sortByOrder(ruleSet.criteria);
    return (
        <div className="row g-3" data-testid="preview-panel">
            <div className="col-12 col-lg-5">
                <div className="app-mobile-record d-flex flex-column gap-3">
                    <div className="fw-semibold">إجابات تجريبية</div>
                    {criteria.length === 0 ? <p className="text-muted mb-0">أضف معايير لتجربة الحساب.</p> : null}
                    {criteria.map((c) => <AnswerInput key={c.id} c={c} value={answers[c.code]} onChange={(v) => setAnswers((a) => ({ ...a, [c.code]: v }))} />)}
                    {criteria.length > 0 ? <div><button type="button" className="btn btn-outline-primary btn-sm" onClick={() => setAnswers({})}>مسح الإجابات</button></div> : null}
                </div>
            </div>
            <div className="col-12 col-lg-7">
                {result ? (
                    <>
                        {result.source === "client" ? <p className="form-text" data-testid="preview-source">محسوبة في المتصفح (خدمة المعاينة غير متاحة بعد).</p> : null}
                        <AssessmentResult breakdown={result.breakdown} />
                    </>
                ) : <p className="text-muted">جارٍ الحساب…</p>}
            </div>
        </div>
    );
}
