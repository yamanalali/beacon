import { useState } from "react";
import { router } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import type { PillarBreakdown, ScoreBreakdown } from "../../types/readiness";
import { StatusBadge } from "../Feedback/StatusBadge";
import type { ReadinessScoreSnapshot, ReadinessScoreSnapshotWithResult, ReadinessSnapshotResult } from "../../types/readiness-snapshot";
import { bandTone } from "./scoring";

/** One null-safe view over a live breakdown or a snapshot `result` (old snapshots: null keys / []). */
type View = {
    score: number | null; band: { label_ar: string | null; name?: string | null; color_token: string | null } | null;
    mandatory: boolean | null; pillars: PillarBreakdown[]; blocking: string[]; unmet_mandatory: string[];
    needs_review: string[]; missing: string[]; unmet: string[]; improvements: ScoreBreakdown["improvements"];
};
const arr = <T,>(v: T[] | null | undefined): T[] => (Array.isArray(v) ? v : []);
export function toView(r: ReadinessSnapshotResult | ScoreBreakdown | null | undefined): View | null {
    if (!r) return null;
    const pillars = arr(r.pillars).length ? arr(r.pillars) : arr((r as ReadinessSnapshotResult).breakdown);
    return {
        score: r.score ?? null, band: r.band ?? null, mandatory: r.mandatory_conditions_met ?? null, pillars: pillars.map((p) => ({ ...p, criteria: arr(p.criteria) })),
        blocking: arr(r.blocking), unmet_mandatory: arr(r.unmet_mandatory), needs_review: arr(r.needs_review), missing: arr(r.missing), unmet: arr(r.unmet), improvements: arr(r.improvements),
    };
}

const fmt = (n: number | string | null | undefined) => (n === null || n === undefined || n === "" ? "-" : Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 }));

/** SVG radar of pillar scores (0..100). Needs at least 3 axes; otherwise the bars below are enough. */
export function RadarChart({ pillars, size = 260 }: { pillars: PillarBreakdown[]; size?: number }) {
    const t = useT();
    if (pillars.length < 3) return null;
    const c = size / 2;
    const r = c - 40;
    const pt = (i: number, v: number) => {
        const a = -Math.PI / 2 - (2 * Math.PI * i) / pillars.length; // counter-clockwise reads naturally in RTL
        return [c + Math.cos(a) * r * v, c + Math.sin(a) * r * v] as const;
    };
    const poly = pillars.map((p, i) => pt(i, (p.score ?? 0) / 100).join(",")).join(" ");
    return (
        <svg viewBox={`0 0 ${size} ${size}`} width="100%" style={{ maxWidth: size }} role="img" aria-label={t("readiness.result.radar_aria", undefined, "مخطط المحاور")} data-testid="radar">
            {[0.25, 0.5, 0.75, 1].map((k) => (
                <polygon key={k} points={pillars.map((_, i) => pt(i, k).join(",")).join(" ")} fill="none" stroke="var(--app-border)" />
            ))}
            {pillars.map((_, i) => { const [x, y] = pt(i, 1); return <line key={i} x1={c} y1={c} x2={x} y2={y} stroke="var(--app-border)" />; })}
            <polygon points={poly} fill="var(--app-primary-soft)" stroke="var(--app-primary)" strokeWidth={2} />
            {pillars.map((p, i) => { const [x, y] = pt(i, 1.18); return <text key={p.code ?? `implicit-${i}`} x={x} y={y} textAnchor="middle" dominantBaseline="middle" fontSize="11" fill="var(--app-text)">{p.label_ar ?? t("readiness.result.unclassified", undefined, "غير مصنف")}</text>; })}
        </svg>
    );
}

function Bar({ value, danger }: { value: number | null; danger?: boolean }) {
    return (
        <div className="progress" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={value ?? 0} style={{ height: 8 }}>
            <div className="progress-bar" style={{ width: `${Math.max(0, Math.min(100, value ?? 0))}%`, background: danger ? "var(--app-danger)" : "var(--app-primary)" }} />
        </div>
    );
}

export function BandBadge({ band, label }: { band?: { label_ar?: string | null; name?: string | null; color_token?: string | null } | null; label?: string | null }) {
    const t = useT();
    const text = band?.label_ar || band?.name || label;
    if (!text) return <StatusBadge label={t("readiness.result.no_band", undefined, "بلا شريحة")} tone="neutral" />;
    return <StatusBadge label={text} tone={bandTone(band?.color_token)} />;
}

type Props = {
    /** Live/preview breakdown (builder preview). */
    breakdown?: ScoreBreakdown | null;
    /** Top-level `result` prop of the assessment page (#75): frozen for finalized, live for drafts. Wins over everything. */
    result?: ReadinessSnapshotResult | null;
    snapshot?: ReadinessScoreSnapshotWithResult | null;
    snapshotHistory?: ReadinessScoreSnapshot[];
    canRecalculate?: boolean;
    assessmentId?: string;
};

export function AssessmentResult({ breakdown: live, result, snapshot, snapshotHistory = [], canRecalculate, assessmentId }: Props) {
    const t = useT();
    const breakdown = toView(result ?? snapshot?.result ?? (snapshot ? null : live));
    const score = breakdown?.score ?? (snapshot?.score !== undefined && snapshot?.score !== null ? Number(snapshot.score) : null);
    const mandatory = breakdown ? (breakdown.unmet_mandatory.length > 0 ? false : breakdown.mandatory) : snapshot?.mandatory_conditions_met ?? null;
    const band = breakdown?.band ?? snapshot?.band_detail ?? null;
    const unmetMandatory = new Set(breakdown?.unmet_mandatory ?? []);
    const blocked = !!breakdown && (breakdown.blocking.length > 0 || breakdown.unmet_mandatory.length > 0);
    const [reason, setReason] = useState("");
    const [asking, setAsking] = useState(false);

    const recalc = () => {
        if (!assessmentId) return;
        router.post(`/readiness-assessments/${assessmentId}/recalculate`, { reason: reason.trim() || null }, { preserveScroll: true, onFinish: () => setAsking(false) });
    };

    return (
        <div className="d-flex flex-column gap-3" data-testid="assessment-result">
            <div className="app-mobile-record">
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <p className="text-muted mb-1">{t("readiness.result.score_label", undefined, "النتيجة")} {snapshot ? t("readiness.result.frozen", undefined, "(مجمّدة)") : t("readiness.result.preview_paren", undefined, "(معاينة)")}</p>
                        <p className="h2 mb-0" data-testid="result-score"><bdi dir="ltr">{fmt(score)}</bdi> <span className="fs-6 text-muted">{t("readiness.result.out_of_100", undefined, "من 100")}</span></p>
                    </div>
                    <div className="d-flex flex-wrap gap-2">
                        <BandBadge band={band} label={snapshot?.band} />
                        <StatusBadge label={mandatory === true ? t("readiness.result.mandatory_met", undefined, "الشروط الإلزامية مستوفاة") : mandatory === false ? t("readiness.result.mandatory_unmet", undefined, "شرط إلزامي غير مستوفى") : t("readiness.result.mandatory_pending", undefined, "الشروط الإلزامية بانتظار المراجعة")} tone={mandatory === true ? "success" : mandatory === false ? "danger" : "warning"} />
                    </div>
                </div>
                <p className="form-text mb-0 mt-2">{t("readiness.result.advisory_disclaimer", undefined, "النتيجة استشارية وليست موافقة تمويل.")}</p>
                {breakdown && blocked ? (
                    <div className="alert alert-danger mt-3 mb-0" role="alert" data-testid="blocking">
                        <strong>{t("readiness.result.blocked_title", undefined, "النتيجة محجوبة:")}</strong>
                        <ul className="mb-0">
                            {breakdown.unmet_mandatory.map((code) => <li key={`m-${code}`}>{t("readiness.result.mandatory_unmet_item", undefined, "شرط إلزامي غير مستوفى:")} {labelOf(breakdown, code)}</li>)}
                            {breakdown.blocking.map((code) => <li key={`p-${code}`}>{t("readiness.result.pillar_below_min", { pillar: breakdown.pillars.find((p) => p.code === code)?.label_ar ?? code }, `المحور «${breakdown.pillars.find((p) => p.code === code)?.label_ar ?? code}» أقل من الحد الأدنى`)}</li>)}
                        </ul>
                    </div>
                ) : null}
            </div>

            {breakdown && breakdown.needs_review.length > 0 ? (
                <div className="app-mobile-record" data-testid="needs-review">
                    <div className="fw-semibold">{t("readiness.result.needs_review", undefined, "بانتظار المراجعة")}</div>
                    <ul className="mb-0">{breakdown.needs_review.map((code) => <li key={code}>{labelOf(breakdown, code)}</li>)}</ul>
                </div>
            ) : null}

            {breakdown && breakdown.pillars.length > 0 ? (
                <>
                    <div className="row g-3">
                        {breakdown.pillars.length >= 3 ? (
                            <div className="col-12 col-lg-5 d-flex justify-content-center"><RadarChart pillars={breakdown.pillars} /></div>
                        ) : null}
                        <div className={breakdown.pillars.length >= 3 ? "col-12 col-lg-7" : "col-12"}>
                            {breakdown.pillars.map((p, pi) => (
                                <details key={p.code ?? `implicit-${pi}`} className="app-mobile-record mb-2" data-testid="pillar-row" open={breakdown.pillars.length <= 2}>
                                    <summary className="d-flex justify-content-between gap-2">
                                        <span className="fw-semibold">{p.label_ar ?? t("readiness.result.uncategorized_criteria", undefined, "معايير غير مصنفة")}{p.blocking ? <span className="text-danger"> {t("readiness.result.below_min_paren", undefined, "(أقل من الحد الأدنى)")}</span> : null}</span>
                                        <bdi dir="ltr">{fmt(p.score)}</bdi>
                                    </summary>
                                    <Bar value={p.score} danger={p.blocking} />
                                    <ul className="list-unstyled mb-0 mt-2">
                                        {p.criteria.map((c) => (
                                            <li key={c.code} className="app-record-line" data-testid="criterion-bar">
                                                <span>{c.label_ar}{unmetMandatory.has(c.code) ? <span className="text-danger"> {t("readiness.result.mandatory_paren", undefined, "(إلزامي)")}</span> : null}</span>
                                                <span className="d-flex flex-column gap-1">
                                                    {c.points === null ? <span className="text-muted">{c.outcome === "needs_review" ? t("readiness.result.needs_review", undefined, "بانتظار المراجعة") : t("readiness.result.excluded_no_data", undefined, "مستبعد (بيانات غير متوفرة)")}</span> : <Bar value={c.points * 100} danger={unmetMandatory.has(c.code)} />}
                                                    <span className="small text-muted">{t("readiness.result.contribution", undefined, "المساهمة:")} <bdi dir="ltr">{fmt(c.contribution)}</bdi>{c.points !== null && breakdown.missing.includes(c.code) ? ` ${t("readiness.result.missing_data_suffix", undefined, "- بيانات ناقصة")}` : ""}</span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            ))}
                        </div>
                    </div>

                    {breakdown.missing.length > 0 ? (
                        <div className="app-mobile-record" data-testid="missing-items">
                            <div className="fw-semibold">{t("readiness.result.missing_data", undefined, "بيانات ناقصة")}</div>
                            <ul className="mb-0">{breakdown.missing.map((code) => <li key={code}>{labelOf(breakdown, code)}</li>)}</ul>
                        </div>
                    ) : null}
                    {breakdown.improvements.length > 0 ? (
                        <div className="app-mobile-record" data-testid="improvements">
                            <div className="fw-semibold">{t("readiness.result.improvements_title", undefined, "ما الذي يرفع النتيجة؟")}</div>
                            <ol className="mb-0">
                                {breakdown.improvements.slice(0, 5).map((i) => (
                                    <li key={i.code}>{breakdown.missing.includes(i.code) ? t("readiness.result.complete_data", undefined, "استكمال بيانات") : breakdown.unmet.includes(i.code) ? t("readiness.result.fulfill", undefined, "استيفاء") : t("readiness.result.improve", undefined, "تحسين")} «{i.label_ar}»: {t("readiness.result.up_to_points", undefined, "حتى")} <bdi dir="ltr">+{fmt(i.potential_gain)}</bdi> {t("readiness.result.points_word", undefined, "نقطة")}</li>
                                ))}
                            </ol>
                        </div>
                    ) : null}
                </>
            ) : snapshot || result !== undefined ? <p className="text-muted mb-0" data-testid="no-breakdown">{t("readiness.result.no_breakdown", undefined, "تفاصيل المحاور غير محفوظة في هذه اللقطة.")}</p> : null}

            {snapshot && canRecalculate ? (
                <div className="app-mobile-record" data-testid="recalculate">
                    {!asking ? (
                        <button type="button" className="btn btn-outline-primary" onClick={() => setAsking(true)}><i className="bi bi-arrow-repeat" aria-hidden="true" /> {t("readiness.result.recalculate", undefined, "إعادة الاحتساب")}</button>
                    ) : (
                        <div className="d-flex flex-column gap-2">
                            <p className="mb-0">{t("readiness.result.recalc_confirm", undefined, "ستُحسب النتيجة بالقواعد الحالية وتُحفظ لقطة جديدة، وتبقى اللقطات السابقة. هل تريد المتابعة؟")}</p>
                            <label className="form-label mb-0" htmlFor="recalc-reason">{t("readiness.result.reason_optional", undefined, "السبب (اختياري)")}</label>
                            <textarea id="recalc-reason" className="form-control" rows={2} maxLength={2000} value={reason} onChange={(e) => setReason(e.target.value)} />
                            <div className="d-flex gap-2">
                                <button type="button" className="btn btn-primary" onClick={recalc}>{t("readiness.result.confirm_recalc", undefined, "تأكيد إعادة الاحتساب")}</button>
                                <button type="button" className="btn btn-outline-primary" onClick={() => setAsking(false)}>{t("common.cancel", undefined, "إلغاء")}</button>
                            </div>
                        </div>
                    )}
                </div>
            ) : null}

            {snapshotHistory.length > 0 ? (
                <div className="app-mobile-record" data-testid="snapshot-history">
                    <div className="fw-semibold">{t("readiness.result.snapshot_history", undefined, "سجل اللقطات")}</div>
                    <ul className="list-unstyled mb-0">
                        {snapshotHistory.map((s) => (
                            <li key={s.id} className="app-record-line">
                                <span><bdi dir="ltr">{s.computed_at ?? "-"}</bdi></span>
                                <span className="d-flex flex-wrap gap-2 align-items-center">
                                    <bdi dir="ltr">{fmt(s.score)}</bdi>
                                    {s.band_detail || s.band ? <BandBadge band={s.band_detail} label={s.band} /> : null}
                                    <StatusBadge label={s.reason === "initial" ? t("readiness.result.initial_calc", undefined, "أول احتساب") : t("readiness.result.recalc_badge", undefined, "إعادة احتساب")} tone="neutral" />
                                    {s.is_current ? <StatusBadge label={t("readiness.result.current_snapshot", undefined, "الحالية")} tone="success" /> : null}
                                    {s.rule_set_version !== null ? <span className="small text-muted">{t("readiness.result.rules_version", { v: s.rule_set_version }, `قواعد v${s.rule_set_version}`)}</span> : null}
                                    {s.computed_by ? <span className="small text-muted">{s.computed_by}</span> : null}
                                    {s.reason_note ? <span className="small">{s.reason_note}</span> : null}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

function labelOf(b: View, code: string): string {
    for (const p of b.pillars) for (const c of p.criteria) if (c.code === code) return c.label_ar;
    return code;
}

