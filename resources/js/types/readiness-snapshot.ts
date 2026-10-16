import type { CriterionBreakdown, PillarBreakdown, ScoreBand, ScoreBreakdown, ScoreImprovement } from "./readiness";

/** Lightweight band summary (backend: ReadinessAssessmentSnapshot::bandSummary). Legacy snapshots only have `name`. */
export type ReadinessSnapshotBand = {
    name: string | null;
    label_ar: string | null;
    color_token: ScoreBand["color_token"] | null;
};

/**
 * Normalised evaluate() result (backend: ReadinessAssessmentSnapshot::presentResult).
 * Frozen for finalized snapshots, live for drafts. Keys added by scoring v2 (#74) are null on older snapshots.
 */
export type ReadinessSnapshotResult = {
    score: ScoreBreakdown["score"];
    band: (Pick<ScoreBand, "label_ar" | "min" | "max"> & { name: string | null; color_token: string | null }) | null;
    mandatory_conditions_met: ScoreBreakdown["mandatory_conditions_met"];
    blocking: ScoreBreakdown["blocking"] | null;
    pillars: PillarBreakdown[] | null;
    breakdown: PillarBreakdown[] | null;
    improvements: ScoreImprovement[] | null;
    earned_weight: number | null;
    counted_weight: number | null;
    unmet_mandatory: string[];
    needs_review: string[];
    missing: string[];
    unmet: string[];
};

export type { CriterionBreakdown, PillarBreakdown, ScoreImprovement };

/** Frozen readiness result summary (backend: ReadinessAssessmentSnapshot::toDisplay). Used for snapshotHistory. */
export type ReadinessScoreSnapshot = {
    id: number;
    score: string | number | null;
    /** Band name column (kept for compatibility). */
    band: string | null;
    band_detail: ReadinessSnapshotBand | null;
    mandatory_conditions_met: boolean | null;
    reason: "initial" | "recalculation";
    reason_note: string | null;
    rule_set_version: number | null;
    config_hash: string;
    is_current: boolean;
    computed_at: string | null;
    computed_by: string | null;
};

/** Current snapshot (backend: ReadinessAssessmentSnapshot::toDisplayWithResult). */
export type ReadinessScoreSnapshotWithResult = ReadinessScoreSnapshot & {
    result: ReadinessSnapshotResult | null;
};
