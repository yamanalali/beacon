import { router } from "@inertiajs/react";
import type { Criterion, Pillar, ScoreBand, ScoreBreakdown } from "../../types/readiness";
import type { PreviewAnswer, RuleSetDraft } from "./types";

/**
 * Real admin endpoints from backend #74 (routes/readiness.php, prefix /admin/readiness).
 * Mutations redirect back to admin.readiness.index; we call them with fetch (JSON accept) so 422s
 * - validation errors AND abort(422) messages (locked set, draft already exists) - come back as JSON,
 * then reload the Inertia props.
 */
export const R = {
    store: () => "/admin/readiness",
    publish: (rs: number) => `/admin/readiness/${rs}/publish`,
    duplicate: (rs: number) => `/admin/readiness/${rs}/duplicate`,
    export: (rs: number) => `/admin/readiness/${rs}/export`,
    import: () => "/admin/readiness/import",
    preview: (rs: number) => `/admin/readiness/${rs}/preview`,
    criteria: (rs: number) => `/admin/readiness/${rs}/criteria`,
    criterion: (id: number) => `/admin/readiness/criteria/${id}`,
    criteriaReorder: (rs: number) => `/admin/readiness/${rs}/criteria/reorder`,
    pillars: (rs: number) => `/admin/readiness/${rs}/pillars`,
    pillar: (id: number) => `/admin/readiness/pillars/${id}`,
    pillarsReorder: (rs: number) => `/admin/readiness/${rs}/pillars/reorder`,
    bands: (rs: number) => `/admin/readiness/${rs}/bands`,
    band: (id: number) => `/admin/readiness/bands/${id}`,
    bandsReorder: (rs: number) => `/admin/readiness/${rs}/bands/reorder`,
};

export type ApiResult = { ok: true } | { ok: false; message: string; errors: Record<string, string> };

function xsrf(): string | undefined {
    const m = typeof document !== "undefined" ? document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) : null;
    return m ? decodeURIComponent(m[1]) : undefined;
}

export function flattenErrors(errors: Record<string, string | string[]> | undefined): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(errors ?? {})) out[k] = Array.isArray(v) ? v[0] ?? "" : v;
    return out;
}

export async function request(method: "POST" | "PUT" | "DELETE", url: string, body?: unknown, fetchImpl: typeof fetch = fetch): Promise<Response> {
    const token = xsrf();
    const isForm = typeof FormData !== "undefined" && body instanceof FormData;
    return fetchImpl(url, {
        method,
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...(isForm || body === undefined ? {} : { "Content-Type": "application/json" }), ...(token ? { "X-XSRF-TOKEN": token } : {}) },
        body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    });
}

/** Sends a mutation; on success reloads the page props. 422/403 come back as a message + field errors. */
export async function mutate(method: "POST" | "PUT" | "DELETE", url: string, body?: unknown, fetchImpl: typeof fetch = fetch): Promise<ApiResult> {
    let res: Response;
    try {
        res = await request(method, url, body, fetchImpl);
    } catch {
        return { ok: false, message: "تعذّر الاتصال بالخادم. حاول مرة أخرى.", errors: {} };
    }
    if (res.status === 202) return { ok: false, message: "حُفظ الطلب على الجهاز بانتظار المزامنة.", errors: {} };
    if (!res.ok) {
        const j = (await res.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
        const errors = flattenErrors(j?.errors);
        const fallback = res.status === 401 || res.status === 419 ? "انتهت الجلسة. حدّث الصفحة وسجّل الدخول ثم حاول مجدداً." : res.status === 403 ? "ليست لديك صلاحية لهذا الإجراء." : res.status === 429 ? "محاولات كثيرة، انتظر قليلاً." : "تعذّر تنفيذ الطلب.";
        return { ok: false, message: Object.keys(errors).length ? "راجع الحقول المعلّمة." : res.status === 422 ? j?.message ?? fallback : fallback, errors };
    }
    if (res.redirected && /\/login(?:[/?#]|$)/.test(res.url)) {
        return { ok: false, message: "انتهت الجلسة. حدّث الصفحة وسجّل الدخول ثم حاول مجدداً.", errors: {} };
    }
    router.reload({ only: ["ruleSets"] });
    return { ok: true };
}

const num = (v: unknown) => (v === "" || v === null || v === undefined ? null : Number(v));

export const pillarBody = (p: Pillar) => ({ code: p.code, label_ar: p.label_ar, weight: num(p.weight), min_score: num(p.min_score) }); // empty = null = equal default share (#82)
export const bandBody = (b: ScoreBand) => ({ label_ar: b.label_ar, min: num(b.min), max: num(b.max), color_token: b.color_token });
export const criterionBody = (c: Criterion) => ({
    code: c.code, label_ar: c.label_ar, description_ar: c.description_ar, weight: num(c.weight), is_mandatory: c.is_mandatory,
    missing_data_treatment: c.missing_data_treatment, compliance_requirement_id: c.compliance_requirement_id, readiness_pillar_id: c.readiness_pillar_id,
    type: c.type, config: c.config,
});

export type BuilderActions = {
    savePillar(rs: RuleSetDraft, p: Pillar): Promise<ApiResult>;
    deletePillar(p: Pillar): Promise<ApiResult>;
    reorderPillars(rs: RuleSetDraft, ids: number[]): Promise<ApiResult>;
    saveCriterion(rs: RuleSetDraft, c: Criterion): Promise<ApiResult>;
    deleteCriterion(c: Criterion): Promise<ApiResult>;
    reorderCriteria(rs: RuleSetDraft, ids: number[]): Promise<ApiResult>;
    saveBand(rs: RuleSetDraft, b: ScoreBand): Promise<ApiResult>;
    deleteBand(b: ScoreBand): Promise<ApiResult>;
    reorderBands(rs: RuleSetDraft, ids: number[]): Promise<ApiResult>;
    duplicate(rs: RuleSetDraft): Promise<ApiResult>;
    importFile(file: File): Promise<ApiResult>;
    publish(rs: RuleSetDraft): Promise<ApiResult>;
    exportUrl(rs: RuleSetDraft): string;
};

export const serverActions: BuilderActions = {
    savePillar: (rs, p) => (p.id > 0 ? mutate("PUT", R.pillar(p.id), pillarBody(p)) : mutate("POST", R.pillars(rs.id), pillarBody(p))),
    deletePillar: (p) => mutate("DELETE", R.pillar(p.id)),
    reorderPillars: (rs, ids) => mutate("POST", R.pillarsReorder(rs.id), { ids }),
    saveCriterion: (rs, c) => (c.id > 0 ? mutate("PUT", R.criterion(c.id), criterionBody(c)) : mutate("POST", R.criteria(rs.id), criterionBody(c))),
    deleteCriterion: (c) => mutate("DELETE", R.criterion(c.id)),
    reorderCriteria: (rs, ids) => mutate("POST", R.criteriaReorder(rs.id), { ids }),
    saveBand: (rs, b) => (b.id > 0 ? mutate("PUT", R.band(b.id), bandBody(b)) : mutate("POST", R.bands(rs.id), bandBody(b))),
    deleteBand: (b) => mutate("DELETE", R.band(b.id)),
    reorderBands: (rs, ids) => mutate("POST", R.bandsReorder(rs.id), { ids }),
    duplicate: (rs) => mutate("POST", R.duplicate(rs.id)),
    importFile: (file) => { const f = new FormData(); f.append("file", file); return mutate("POST", R.import(), f); },
    publish: (rs) => mutate("POST", R.publish(rs.id)),
    exportUrl: (rs) => R.export(rs.id),
};

/** Answers keyed by criterion code -> preview body keyed by criterion id (null/"" = missing). */
export function toPreviewAnswers(rs: RuleSetDraft, answers: Record<string, unknown>): PreviewAnswer[] {
    return rs.criteria.filter((c) => c.id > 0).map((c) => {
        const v = answers[c.code];
        return { criterion_id: c.id, value: v === "" || v === undefined ? null : v };
    });
}

export class PreviewRejected extends Error {
    constructor(public messages: string[]) { super(messages.join(" ")); }
}

/** POST {ruleSet}/preview -> { evaluation }. 422 = rules not valid yet (publish validation runs first). */
export async function serverPreviewRequest(rs: RuleSetDraft, answers: Record<string, unknown>, fetchImpl: typeof fetch = fetch): Promise<ScoreBreakdown> {
    const res = await request("POST", R.preview(rs.id), { answers: toPreviewAnswers(rs, answers) }, fetchImpl);
    if (res.status === 422) {
        const j = (await res.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
        const msgs = Object.values(flattenErrors(j?.errors));
        throw new PreviewRejected(msgs.length ? msgs : [j?.message ?? "القواعد غير مكتملة."]);
    }
    if (!res.ok) throw new Error(`preview ${res.status}`);
    const body = (await res.json()) as { evaluation: ScoreBreakdown };
    return body.evaluation;
}
