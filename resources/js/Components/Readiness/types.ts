import type { Criterion, CriterionType, Pillar, ScoreBand, ScoreBreakdown } from "../../types/readiness";
import type { ReadinessScoreSnapshot } from "../../types/readiness-snapshot";

/**
 * UI-only shapes for the rule-set builder that types/readiness.ts (backend source of truth) does not
 * define. Kept here so types/readiness.ts stays identical to the backend's file.
 */

/** #13 design-system colour tokens (resources/css/app.css :root). ScoreBand.color_token holds one of these. */
export type ColorToken = "primary" | "success" | "warning" | "danger" | "muted";

export type RuleSetStatus = "draft" | "published" | "retired";

/** A rule set with its v2 children, as the builder edits it. */
export type RuleSetDraft = {
    id: number;
    version_number: number;
    name_ar: string;
    status: RuleSetStatus | string;
    change_summary?: string | null;
    pillars: Pillar[];
    criteria: Criterion[];
    bands: ScoreBand[];
};

export type AnswerValue = boolean | number | string | null | undefined;
/** Test answers keyed by criterion code. null/undefined = missing data. */
export type Answers = Record<string, AnswerValue>;

/** Snapshot plus an optional stored breakdown (not sent by the backend yet). */
export type SnapshotWithBreakdown = ReadinessScoreSnapshot & { breakdown?: ScoreBreakdown | null };

/** JSON import/export envelope (no backend endpoint yet). */
export type RuleSetExport = {
    format: "beacon.readiness.rule-set";
    version: 1;
    rule_set: {
        name_ar: string;
        change_summary: string | null;
        pillars: Omit<Pillar, "id" | "readiness_rule_set_id" | "criteria">[];
        criteria: (Omit<Criterion, "id" | "readiness_rule_set_id" | "readiness_pillar_id"> & { pillar_code: string | null; type: CriterionType })[];
        bands: Omit<ScoreBand, "id" | "readiness_rule_set_id">[];
    };
};
