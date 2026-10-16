import type { ScoreBreakdown } from "../../types/readiness";
import type { Answers, RuleSetDraft as RuleSet } from "./types";
import { evaluate } from "./scoring";

export type PreviewSource = "server" | "client";
export type PreviewResult = { breakdown: ScoreBreakdown; source: PreviewSource };
export type PreviewFetcher = (ruleSet: RuleSet, answers: Answers) => Promise<ScoreBreakdown>;

/**
 * Proposed endpoint (does not exist yet on main).
 * TODO: align path/payload with the backend: POST /admin/readiness/rule-sets/{id}/preview { answers, rule_set? }.
 * Unsaved drafts send their current definition so the server can score what is on screen.
 */
export function previewUrl(ruleSetId: number): string {
    return `/admin/readiness/rule-sets/${ruleSetId}/preview`;
}

function xsrf(): string | undefined {
    const m = typeof document !== "undefined" ? document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) : null;
    return m ? decodeURIComponent(m[1]) : undefined;
}

export const serverPreview: PreviewFetcher = async (ruleSet, answers) => {
    const token = xsrf();
    const res = await fetch(previewUrl(ruleSet.id), {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...(token ? { "X-XSRF-TOKEN": token } : {}) },
        body: JSON.stringify({ answers, rule_set: ruleSet.status === "draft" ? ruleSet : undefined }),
    });
    if (!res.ok) throw new Error(`preview ${res.status}`);
    const body = (await res.json()) as { breakdown?: ScoreBreakdown } & Partial<ScoreBreakdown>;
    return (body.breakdown ?? body) as ScoreBreakdown;
};

/** Server first; falls back to the client mirror when the endpoint is missing or fails. */
export async function runPreview(ruleSet: RuleSet, answers: Answers, fetcher: PreviewFetcher | null = serverPreview): Promise<PreviewResult> {
    if (fetcher) {
        try {
            return { breakdown: await fetcher(ruleSet, answers), source: "server" };
        } catch {
            /* fall through to client calculation */
        }
    }
    return { breakdown: evaluate(ruleSet, answers), source: "client" };
}
