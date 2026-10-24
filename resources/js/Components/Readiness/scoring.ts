import type { ChoiceOption, Criterion, CriterionBreakdown, CriterionConfig, CriterionType, NumericBand, Pillar, PillarBreakdown, ResultOutcome, ScoreBand, ScoreBreakdown, ScoreImprovement } from "../../types/readiness";
import { IMPLICIT_PILLAR, type Answers, type AnswerValue, type ColorToken, type RuleSetDraft, type RuleSetExport } from "./types";

/**
 * Client-side mirror of the scoring described for ReadinessScorer v2. The server preview is the source of
 * truth; this is only the fallback while POST .../preview does not exist. No business weights or thresholds
 * live here: every number comes from the rule set Beacon entered.
 */

export const COLOR_TOKENS: { token: ColorToken; label_ar: string }[] = [
    { token: "success", label_ar: "أخضر (نجاح)" },
    { token: "primary", label_ar: "الأساسي" },
    { token: "warning", label_ar: "كهرماني (تنبيه)" },
    { token: "danger", label_ar: "أحمر (خطر)" },
    { token: "muted", label_ar: "رمادي (محايد)" },
];
const TOKENS = new Set<string>(COLOR_TOKENS.map((c) => c.token));
export const isColorToken = (t: string): t is ColorToken => TOKENS.has(t);

export function colorVar(token: string): string {
    if (!isColorToken(token) || token === "muted") return "var(--app-text-muted)";
    return `var(--app-${token})`;
}

/** Band colour -> existing #13 status badge tone. */
export function bandTone(token: string | undefined | null): "success" | "warning" | "danger" | "neutral" {
    return token === "success" || token === "primary" ? "success" : token === "warning" ? "warning" : token === "danger" ? "danger" : "neutral";
}

export const CRITERION_TYPES: { type: CriterionType; label_ar: string }[] = [
    { type: "boolean", label_ar: "نعم / لا" },
    { type: "numeric_bands", label_ar: "شرائح رقمية" },
    { type: "choice", label_ar: "اختيار من قائمة" },
    { type: "target_ratio", label_ar: "نسبة من الهدف" },
    { type: "manual_scale", label_ar: "تقييم يدوي 1-5" },
];

export const TREATMENT_LABELS: Record<string, string> = {
    counts_as_unmet: "تُعامل كغير مستوفاة",
    excluded: "تُستبعد من الحساب",
    needs_review: "تحتاج مراجعة",
};

export const num = (v: string | number | null | undefined): number => (v === null || v === undefined || v === "" ? Number.NaN : Number(v));

/** Empty config for a type. Targets and points are left for Beacon to fill (validation flags them). */
export function defaultConfig(type: CriterionType): CriterionConfig {
    switch (type) {
        case "boolean": return { type, config: null };
        case "numeric_bands": return { type, config: { unit: "", bands: [] } };
        case "choice": return { type, config: { options: [] } };
        // TODO: BUSINESS DECISION REQUIRED - target per criterion; 0 = not set. cap 1 = full points at target (schema proposal).
        case "target_ratio": return { type, config: { target: 0, direction: "higher", cap: 1 } };
        case "manual_scale": return { type, config: { min: 1, max: 5 } };
    }
}

const clamp01 = (n: number) => Math.max(0, Math.min(1, n));

export function isMissing(v: AnswerValue): boolean {
    return v === null || v === undefined || (typeof v === "string" && v.trim() === "") || (typeof v === "number" && Number.isNaN(v));
}

function toNumber(v: AnswerValue): number | null {
    if (typeof v === "number") return Number.isFinite(v) ? v : null;
    if (typeof v === "string" && v.trim() !== "") {
        const n = Number(v);
        return Number.isFinite(n) ? n : null;
    }
    return null;
}

export const inNumericBand = (b: NumericBand, v: number) => (b.min === null || v >= b.min) && (b.max === null || v <= b.max);

/** Points 0..1 for an answer, or null if it is missing / cannot be scored. */
export function criterionPoints(c: Criterion, v: AnswerValue): number | null {
    if (isMissing(v)) return null;
    switch (c.type) {
        case "boolean":
            if (typeof v === "boolean") return v ? 1 : 0;
            return v === "1" || v === "true" ? 1 : v === "0" || v === "false" ? 0 : null;
        case "numeric_bands": {
            const n = toNumber(v);
            if (n === null) return null;
            const band = c.config.bands.find((b) => inNumericBand(b, n));
            return band ? clamp01(band.points) : 0;
        }
        case "choice": {
            const o = c.config.options.find((x) => x.value === String(v));
            return o ? clamp01(o.points) : null;
        }
        case "target_ratio": {
            const n = toNumber(v);
            const { target, direction, cap } = c.config;
            if (n === null || !(target > 0)) return null;
            const ratio = direction === "higher" ? n / target : n <= 0 ? cap : target / n;
            return clamp01(Math.min(ratio, cap));
        }
        case "manual_scale": {
            const n = toNumber(v);
            const { min, max } = c.config;
            if (n === null || n < min || n > max || max <= min) return null;
            return (n - min) / (max - min);
        }
    }
}

/**
 * Mandatory gate.
 * TODO: BUSINESS DECISION REQUIRED - whether partial points can satisfy a mandatory criterion. For now only full points pass.
 */
const passesMandatory = (points: number) => points >= 1;

export function sortByOrder<T extends { sort_order: number }>(xs: T[]): T[] {
    return [...xs].sort((a, b) => a.sort_order - b.sort_order);
}

export function bandFor(bands: ScoreBand[], score: number | null): ScoreBand | null {
    if (score === null) return null;
    const s = [...bands].sort((a, b) => num(a.min) - num(b.min));
    return s.find((b, i) => score >= num(b.min) && (score < num(b.max) || (i === s.length - 1 && score <= num(b.max)))) ?? null;
}

/**
 * Pillar weights, mirroring ReadinessScorer (backend #74 @ 21cfd16, manager decision in BEACON-DECISIONS.md):
 * a pillar without an admin weight (empty/0, including the implicit pillar of pillar-less criteria) gets an equal
 * share: 1 when no pillar has a weight, otherwise the average of the admin-set weights. Only pillars that hold
 * criteria take part (same as the server). Criterion weights have no default: they must be > 0.
 */
export function resolvePillarWeights(weights: (number | null)[]): number[] {
    const set = weights.filter((w): w is number => w !== null && Number.isFinite(w) && w > 0);
    const fallback = set.length === 0 ? 1 : set.reduce((a, b) => a + b, 0) / set.length;
    return weights.map((w) => (w !== null && Number.isFinite(w) && w > 0 ? w : fallback));
}

/** Criteria without a known pillar go to an implicit pillar (code "_default"). Pillars without criteria are left out. */
export function groupPillars(rs: Pick<RuleSetDraft, "pillars" | "criteria">): { pillar: Pillar | null; weight: number; criteria: Criterion[] }[] {
    const pillars = sortByOrder(rs.pillars);
    const known = new Set(pillars.map((p) => p.id));
    const groups: { pillar: Pillar | null; raw: number | null; criteria: Criterion[] }[] = pillars
        .map((pillar) => ({ pillar, raw: Number.isFinite(num(pillar.weight)) ? num(pillar.weight) : null, criteria: sortByOrder(rs.criteria.filter((c) => c.readiness_pillar_id === pillar.id)) }))
        .filter((g) => g.criteria.length > 0);
    const loose = sortByOrder(rs.criteria.filter((c) => c.readiness_pillar_id === null || !known.has(c.readiness_pillar_id)));
    if (loose.length > 0) groups.push({ pillar: null, raw: null, criteria: loose });
    const weights = resolvePillarWeights(groups.map((g) => g.raw));
    return groups.map((g, i) => ({ pillar: g.pillar, weight: weights[i], criteria: g.criteria }));
}

const round2 = (n: number) => Math.round(n * 100) / 100;

export function evaluate(rs: Pick<RuleSetDraft, "pillars" | "criteria" | "bands">, answers: Answers): ScoreBreakdown {
    const missing: string[] = [];
    const needsReview: string[] = [];
    const unmet: string[] = [];
    const unmetMandatory: string[] = [];
    const blocking: string[] = [];
    let earnedAll = 0;
    let countedAll = 0;

    const work = groupPillars(rs).map(({ pillar, weight, criteria }) => {
        let counted = 0;
        let earned = 0;
        const rows: CriterionBreakdown[] = criteria.map((c) => {
            const w = num(c.weight);
            const value = answers[c.code] ?? null;
            let points = criterionPoints(c, value);
            let outcome: ResultOutcome;
            if (points === null) {
                missing.push(c.code);
                if (c.missing_data_treatment === "counts_as_unmet") {
                    points = 0;
                    outcome = "not_met";
                } else if (c.missing_data_treatment === "excluded") {
                    outcome = "missing";
                } else {
                    outcome = "needs_review";
                    needsReview.push(c.code);
                }
            } else {
                outcome = points > 0 ? "met" : "not_met";
            }
            if (points !== null) {
                counted += w;
                earned += w * points;
                if (points === 0) unmet.push(c.code);
                if (c.is_mandatory && !passesMandatory(points)) unmetMandatory.push(c.code);
            }
            return { code: c.code, label_ar: c.label_ar, type: c.type, value, points, weight: w, contribution: 0, outcome };
        });
        earnedAll += earned;
        countedAll += counted;
        const score = counted > 0 ? round2((earned / counted) * 100) : null;
        const min = pillar && pillar.min_score !== null && pillar.min_score !== "" ? num(pillar.min_score) : null;
        const below = min !== null && score !== null && score < min;
        if (below && pillar) blocking.push(pillar.code);
        const pb: PillarBreakdown = { code: pillar?.code ?? IMPLICIT_PILLAR, label_ar: pillar?.label_ar ?? null, weight, score, min_score: min, blocking: below, contribution: 0, criteria: rows };
        return { pb, counted };
    });

    const scoredWeight = work.filter((w) => w.pb.score !== null).reduce((s, w) => s + w.pb.weight, 0);
    const score = scoredWeight > 0 ? round2(work.reduce((s, w) => s + (w.pb.score === null ? 0 : w.pb.weight * w.pb.score), 0) / scoredWeight) : null;

    const improvements: ScoreImprovement[] = [];
    const allWeight = work.reduce((s, w) => s + w.pb.weight, 0);
    for (const w of work) {
        const share = scoredWeight > 0 && w.pb.score !== null ? w.pb.weight / scoredWeight : allWeight > 0 ? w.pb.weight / allWeight : 0;
        w.pb.contribution = w.pb.score === null ? 0 : round2((w.pb.score * share));
        for (const r of w.pb.criteria) {
            const denom = w.counted + (r.points === null ? r.weight : 0);
            const unit = denom > 0 ? (r.weight / denom) * share * 100 : 0;
            r.contribution = r.points === null ? 0 : round2(r.points * unit);
            const gain = round2((1 - (r.points ?? 0)) * unit);
            if (gain > 0) improvements.push({ code: r.code, label_ar: r.label_ar, potential_gain: gain });
        }
    }
    improvements.sort((a, b) => b.potential_gain - a.potential_gain);
    const band = bandFor(rs.bands, score);

    return {
        score,
        band: band ? { label_ar: band.label_ar, min: band.min, max: band.max, color_token: band.color_token } : null,
        // Server: null while a mandatory criterion still needs review.
        mandatory_conditions_met: unmetMandatory.length > 0 ? false : rs.criteria.some((c) => c.is_mandatory && needsReview.includes(c.code)) ? null : true,
        blocking,
        pillars: work.map((w) => w.pb),
        breakdown: work.map((w) => w.pb),
        improvements,
        earned_weight: round2(earnedAll),
        counted_weight: round2(countedAll),
        unmet_mandatory: unmetMandatory,
        needs_review: needsReview,
        missing,
        unmet,
    };
}

/** Blocked = an unmet mandatory criterion or a pillar under its minimum. */
export const isBlocked = (b: ScoreBreakdown) => b.blocking.length > 0 || b.unmet_mandatory.length > 0;

/* ---------- pre-publish validation (client mirror of the backend publish rules) ---------- */

export type ValidationIssue = { path: string; message: string };

/** Same text as the server publish error (backend #74). */
export const NO_BANDS_MESSAGE = "لا يمكن نشر القواعد بدون نطاقات تصنيف.";

export function validateBands(bands: ScoreBand[], forPublish = true): ValidationIssue[] {
    const out: ValidationIssue[] = [];
    const err = (message: string) => out.push({ path: "bands", message });
    if (bands.length === 0) {
        // Server: bands are required to publish only; preview and import allow none.
        if (forPublish) err(NO_BANDS_MESSAGE);
        return out;
    }
    if (bands.some((b) => !Number.isFinite(num(b.min)) || !Number.isFinite(num(b.max)))) err("حدد بداية ونهاية كل شريحة.");
    bands.forEach((b) => { if (!b.label_ar.trim()) err("لكل شريحة اسم."); if (!isColorToken(b.color_token)) err(`لون الشريحة «${b.label_ar}» ليس من ألوان النظام.`); });
    const s = bands.filter((b) => Number.isFinite(num(b.min)) && Number.isFinite(num(b.max))).sort((a, b) => num(a.min) - num(b.min));
    if (s.length === 0) return out;
    s.forEach((b) => {
        if (num(b.min) >= num(b.max)) err(`الشريحة «${b.label_ar}»: البداية يجب أن تكون أقل من النهاية.`);
        if (num(b.min) < 0 || num(b.max) > 100) err(`الشريحة «${b.label_ar}» خارج النطاق 0-100.`);
    });
    if (num(s[0].min) > 0) err(`فجوة: النطاق من 0 إلى ${num(s[0].min)} غير مغطى.`);
    if (num(s[s.length - 1].max) < 100) err(`فجوة: النطاق من ${num(s[s.length - 1].max)} إلى 100 غير مغطى.`);
    for (let i = 1; i < s.length; i++) {
        const prev = num(s[i - 1].max);
        const cur = num(s[i].min);
        if (cur < prev) err(`الشريحتان «${s[i - 1].label_ar}» و«${s[i].label_ar}» متداخلتان.`);
        if (cur > prev) err(`فجوة بين ${prev} و${cur}.`);
    }
    return out;
}

const dupes = (xs: string[]) => [...new Set(xs.filter((x, i) => xs.indexOf(x) !== i))];

export function validateRuleSet(rs: Pick<RuleSetDraft, "pillars" | "criteria" | "bands">, forPublish = true): ValidationIssue[] {
    const out: ValidationIssue[] = [];
    const err = (path: string, message: string) => out.push({ path, message });
    if (rs.criteria.length === 0) err("criteria", "أضف معياراً واحداً على الأقل.");
    rs.pillars.forEach((p) => {
        const n = p.label_ar || p.code;
        if (!p.code.trim()) err(`pillar.${p.id}`, "لكل محور رمز.");
        // Empty/0 = equal default share (server rule); only negative or non-numeric is an error.
        if (p.weight !== null && p.weight !== "" && !(num(p.weight) >= 0)) err(`pillar.${p.code}`, `وزن المحور «${n}» لا يمكن أن يكون سالباً.`);
        if (p.min_score !== null && p.min_score !== "" && !(num(p.min_score) >= 0 && num(p.min_score) <= 100)) err(`pillar.${p.code}`, `الحد الأدنى للمحور «${n}» بين 0 و100.`);
    });
    dupes(rs.pillars.map((p) => p.code)).forEach((c) => err(`pillar.${c}`, `رمز المحور «${c}» مكرر.`));
    dupes(rs.criteria.map((c) => c.code)).forEach((c) => err(`criterion.${c}`, `رمز المعيار «${c}» مكرر.`));
    const pts = (p: number) => Number.isFinite(p) && p >= 0 && p <= 1;

    rs.criteria.forEach((c) => {
        const n = c.label_ar || c.code;
        const path = `criterion.${c.code}`;
        if (!c.code.trim()) err(path, "لكل معيار رمز.");
        if (!(num(c.weight) > 0)) err(path, `وزن المعيار «${n}» يجب أن يكون أكبر من صفر.`);
        if (c.type === "numeric_bands") {
            if (c.config.bands.length === 0) err(path, `أضف شرائح للمعيار «${n}».`);
            if (c.config.bands.some((b) => !pts(b.points))) err(path, `نقاط الشرائح في «${n}» بين 0 و1.`);
            const s = [...c.config.bands].sort((a, b) => (a.min ?? -Infinity) - (b.min ?? -Infinity));
            for (let i = 1; i < s.length; i++) if ((s[i].min ?? -Infinity) <= (s[i - 1].max ?? Infinity)) { err(path, `شرائح «${n}» متداخلة.`); break; }
        }
        if (c.type === "choice") {
            if (c.config.options.length === 0) err(path, `أضف خيارات للمعيار «${n}».`);
            if (c.config.options.some((o: ChoiceOption) => !pts(o.points))) err(path, `نقاط الخيارات في «${n}» بين 0 و1.`);
            if (c.config.options.some((o: ChoiceOption) => !o.value.trim())) err(path, `لكل خيار في «${n}» قيمة.`);
            dupes(c.config.options.map((o: ChoiceOption) => o.value)).forEach((v) => err(path, `القيمة «${v}» مكررة في «${n}».`));
        }
        if (c.type === "target_ratio" && !(c.config.target > 0)) err(path, `حدد هدفاً أكبر من صفر للمعيار «${n}».`);
    });
    out.push(...validateBands(rs.bands, forPublish));
    return out;
}

/* ---------- JSON import / export (server format: ReadinessRuleSetValidator::definition) ---------- */

export const EXPORT_FORMAT = "beacon.readiness.rule_set";

export function exportRuleSet(rs: RuleSetDraft): RuleSetExport {
    const codeOf = new Map(rs.pillars.map((p) => [p.id, p.code]));
    return {
        format: EXPORT_FORMAT,
        format_version: 1,
        name_ar: rs.name_ar,
        change_summary: rs.change_summary ?? null,
        source_version_number: rs.version_number,
        pillars: sortByOrder(rs.pillars).map((p) => ({ code: p.code, label_ar: p.label_ar, weight: p.weight === null || p.weight === "" ? null : num(p.weight), min_score: p.min_score === null || p.min_score === "" ? null : num(p.min_score), sort_order: p.sort_order })),
        criteria: sortByOrder(rs.criteria).map(({ id: _i, readiness_rule_set_id: _r, readiness_pillar_id, compliance_requirement_id: _c, ...c }) => ({ ...c, weight: num(c.weight), pillar_code: readiness_pillar_id !== null ? codeOf.get(readiness_pillar_id) ?? null : null })),
        bands: sortByOrder(rs.bands).map((b) => ({ label_ar: b.label_ar, min: num(b.min), max: num(b.max), color_token: b.color_token, sort_order: b.sort_order })),
    };
}

export type ImportResult = { ok: true; draft: RuleSetDraft; issues: ValidationIssue[] } | { ok: false; error: string };

/** Parses an export file and runs the same checks as publishing (client-side, before uploading). */
export function importRuleSet(json: string, base: { id: number; version_number: number }): ImportResult {
    let data: unknown;
    try {
        data = JSON.parse(json);
    } catch {
        return { ok: false, error: "تعذرت قراءة ملف القواعد. اختر ملفاً سبق تنزيله من النظام." };
    }
    try { return importedDraft(data, base); }
    catch { return { ok: false, error: "بيانات ملف القواعد غير صالحة. اختر ملفاً سبق تنزيله من النظام." }; }
}

function importedDraft(data: unknown, base: { id: number; version_number: number }): ImportResult {
    const r = data as Partial<RuleSetExport> | null;
    if (!r || r.format !== EXPORT_FORMAT || r.format_version !== 1) return { ok: false, error: "صيغة الملف غير معروفة." };
    if (!Array.isArray(r.pillars) || !Array.isArray(r.criteria) || !Array.isArray(r.bands)) return { ok: false, error: "الملف ناقص: المحاور أو المعايير أو الشرائح." };
    const types = new Set<string>(CRITERION_TYPES.map((t) => t.type));
    if (r.criteria.some((c) => !types.has(c.type))) return { ok: false, error: "نوع معيار غير معروف في الملف." };
    const pillars: Pillar[] = r.pillars.map((p, i) => ({ ...p, id: -(i + 1), readiness_rule_set_id: base.id, min_score: p.min_score ?? null }));
    const idOf = new Map(pillars.map((p) => [p.code, p.id]));
    const criteria = r.criteria.map(({ pillar_code, compliance_requirement_code: _cc, ...c }, i) => ({ ...c, id: -(i + 1), readiness_rule_set_id: base.id, compliance_requirement_id: null, description_ar: c.description_ar ?? null, readiness_pillar_id: pillar_code ? idOf.get(pillar_code) ?? null : null }) as Criterion);
    const bands: ScoreBand[] = r.bands.map((b, i) => ({ ...b, id: -(i + 1), readiness_rule_set_id: base.id }));
    const draft: RuleSetDraft = { id: base.id, version_number: base.version_number, name_ar: r.name_ar ?? "", status: "draft", change_summary: r.change_summary ?? null, pillars, criteria, bands };
    return { ok: true, draft, issues: validateRuleSet(draft, false) };
}

/** Moves one item and renumbers sort_order 0..n-1. */
export function reorder<T extends { sort_order: number }>(items: T[], from: number, to: number): T[] {
    const list = sortByOrder(items);
    if (from !== to && from >= 0 && to >= 0 && from < list.length && to < list.length) {
        const [moved] = list.splice(from, 1);
        list.splice(to, 0, moved);
    }
    return list.map((x, i) => ({ ...x, sort_order: i }));
}
