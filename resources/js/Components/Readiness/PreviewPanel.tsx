import { useEffect, useState } from "react";
import { useT } from "../../Hooks/useT";
import type { Criterion } from "../../types/readiness";
import type { Answers, AnswerValue, RuleSetDraft as RuleSet } from "./types";
import { AssessmentResult } from "./AssessmentResult";
import { runPreview, serverPreview, type PreviewFetcher, type PreviewResult } from "./previewApi";
import { sortByOrder } from "./scoring";

function AnswerInput({ c, value, onChange }: { c: Criterion; value: AnswerValue; onChange: (v: AnswerValue) => void }) {
    const t = useT();
    const id = `ans-${c.code}`;
    const label = <label className="form-label" htmlFor={id}>{c.label_ar || c.code}{c.is_mandatory ? " *" : ""}</label>;
    const str = value === null || value === undefined ? "" : String(value);
    const noData = t("readiness.preview.no_data", undefined, "بيانات غير متوفرة");
    const yes = t("common.yes", undefined, "نعم");
    const no = t("common.no", undefined, "لا");
    switch (c.type) {
        case "boolean":
            return (
                <div>{label}
                    <select id={id} className="form-select" value={str} onChange={(e) => onChange(e.target.value === "" ? null : e.target.value === "true")}>
                        <option value="">{noData}</option><option value="true">{yes}</option><option value="false">{no}</option>
                    </select>
                </div>
            );
        case "choice":
            return (
                <div>{label}
                    <select id={id} className="form-select" value={str} onChange={(e) => onChange(e.target.value === "" ? null : e.target.value)}>
                        <option value="">{noData}</option>
                        {c.config.options.map((o) => <option key={o.value} value={o.value}>{o.label_ar || o.value}</option>)}
                    </select>
                </div>
            );
        case "manual_scale":
            return (
                <div>{label}
                    <select id={id} className="form-select" value={str} onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))}>
                        <option value="">{noData}</option>
                        {Array.from({ length: Math.max(0, c.config.max - c.config.min + 1) }, (_, i) => c.config.min + i).map((n) => <option key={n} value={n}>{n}{c.config.labels_ar?.[String(n)] ? ` - ${c.config.labels_ar[String(n)]}` : ""}</option>)}
                    </select>
                </div>
            );
        default:
            return (
                <div>{label}
                    <div className="input-group">
                        <input id={id} type="number" step="any" dir="ltr" className="form-control" placeholder={noData} value={str} onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))} />
                        {c.type === "numeric_bands" && c.config.unit ? <span className="input-group-text">{c.config.unit}</span> : null}
                        {c.type === "target_ratio" && c.config.target > 0 ? <span className="input-group-text">{t("readiness.preview.target", undefined, "الهدف")} <bdi dir="ltr">&nbsp;{c.config.target}</bdi></span> : null}
                    </div>
                </div>
            );
    }
}

/** Test answers -> server preview (client mirror as fallback while the endpoint does not exist). */
export function PreviewPanel({ ruleSet, fetcher = serverPreview, delay = 300 }: { ruleSet: RuleSet; fetcher?: PreviewFetcher | null; delay?: number }) {
    const t = useT();
    const [answers, setAnswers] = useState<Answers>({});
    const [result, setResult] = useState<PreviewResult | null>(null);

    useEffect(() => {
        let alive = true;
        const timer = setTimeout(() => { void runPreview(ruleSet, answers, fetcher).then((r) => alive && setResult(r)); }, delay);
        return () => { alive = false; clearTimeout(timer); };
    }, [ruleSet, answers, fetcher, delay]);

    const criteria = sortByOrder(ruleSet.criteria);
    return (
        <div className="row g-3" data-testid="preview-panel">
            <div className="col-12 col-lg-5">
                <div className="app-mobile-record d-flex flex-column gap-3">
                    <div className="fw-semibold">{t("readiness.preview.sample_answers", undefined, "إجابات تجريبية")}</div>
                    {criteria.length === 0 ? <p className="text-muted mb-0">{t("readiness.preview.add_criteria", undefined, "أضف معايير لتجربة الحساب.")}</p> : null}
                    {criteria.map((c) => <AnswerInput key={c.id} c={c} value={answers[c.code]} onChange={(v) => setAnswers((a) => ({ ...a, [c.code]: v }))} />)}
                    {criteria.length > 0 ? <div><button type="button" className="btn btn-outline-primary btn-sm" onClick={() => setAnswers({})}>{t("readiness.preview.clear_answers", undefined, "مسح الإجابات")}</button></div> : null}
                </div>
            </div>
            <div className="col-12 col-lg-7">
                {!result ? <p className="text-muted">{t("readiness.preview.calculating", undefined, "جارٍ الحساب…")}</p> : "rejected" in result ? (
                    <div className="alert alert-warning" role="status" data-testid="preview-rejected">
                        <strong>{t("readiness.preview.cannot_preview", undefined, "لا يمكن المعاينة قبل إكمال القواعد:")}</strong>
                        <ul className="mb-0">{result.rejected.map((m, i) => <li key={i}>{m}</li>)}</ul>
                    </div>
                ) : (
                    <>
                        {result.source === "client" ? <p className="form-text" data-testid="preview-source">{t("readiness.preview.client_source", undefined, "محسوبة في المتصفح (تعذّر الوصول إلى خدمة المعاينة).")}</p> : null}
                        <AssessmentResult breakdown={result.breakdown} />
                    </>
                )}
            </div>
        </div>
    );
}
