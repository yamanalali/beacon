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

/** Export/import file = ReadinessRuleSetValidator::definition (backend #74). */
export type RuleSetExport = {
    format: "beacon.readiness.rule_set";
    format_version: 1;
    name_ar: string;
    change_summary: string | null;
    source_version_number?: number | null;
    pillars: { code: string; label_ar: string; weight: number | string; min_score: number | string | null; sort_order: number }[];
    criteria: (Omit<Criterion, "id" | "readiness_rule_set_id" | "readiness_pillar_id" | "compliance_requirement_id"> & { pillar_code: string | null; compliance_requirement_code?: string | null; type: CriterionType })[];
    bands: { label_ar: string; min: number | string; max: number | string; color_token: string; sort_order: number }[];
};

/** POST /admin/readiness/{ruleSet}/preview body item (backend #74; mirrors its readiness.ts PreviewAnswer). */
export type PreviewAnswer = { criterion_id: number; outcome?: "met" | "not_met" | "missing" | "needs_review" | null; value?: unknown };

/** Server band as sent by #74 evaluate(): `name` mirrors label_ar. */
export type BreakdownBand = NonNullable<ScoreBreakdown["band"]> & { name?: string };

/** Implicit pillar code used by the backend scorer (ReadinessScorer::IMPLICIT_PILLAR). */
export const IMPLICIT_PILLAR = "_default";
