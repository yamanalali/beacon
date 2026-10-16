/** Frozen readiness result (backend: ReadinessAssessmentSnapshot::toDisplay). */
export type ReadinessScoreSnapshot = {
    id: number;
    score: string | number | null;
    band: string | null;
    mandatory_conditions_met: boolean | null;
    reason: "initial" | "recalculation";
    reason_note: string | null;
    rule_set_version: number | null;
    config_hash: string;
    is_current: boolean;
    computed_at: string | null;
    computed_by: string | null;
};
