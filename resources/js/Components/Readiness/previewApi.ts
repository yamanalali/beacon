import type { ScoreBreakdown } from "../../types/readiness";
import { PreviewRejected, serverPreviewRequest } from "./adminApi";
import { evaluate } from "./scoring";
import type { Answers, RuleSetDraft as RuleSet } from "./types";

export type PreviewSource = "server" | "client";
export type PreviewResult = { breakdown: ScoreBreakdown; source: PreviewSource } | { rejected: string[] };
export type PreviewFetcher = (ruleSet: RuleSet, answers: Answers) => Promise<ScoreBreakdown>;

/** Real endpoint (backend #74): POST /admin/readiness/{ruleSet}/preview. */
export const serverPreview: PreviewFetcher = (rs, answers) => serverPreviewRequest(rs, answers);

/**
 * Server first. A 422 (rules not valid yet) is shown as messages, never replaced by a client score.
 * The client mirror is only used when the server cannot be reached (offline / 404 before #74 is deployed).
 */
export async function runPreview(ruleSet: RuleSet, answers: Answers, fetcher: PreviewFetcher | null = serverPreview): Promise<PreviewResult> {
    if (fetcher) {
        try {
            return { breakdown: await fetcher(ruleSet, answers), source: "server" };
        } catch (e) {
            if (e instanceof PreviewRejected) return { rejected: e.messages };
        }
    }
    return { breakdown: evaluate(ruleSet, answers), source: "client" };
}
