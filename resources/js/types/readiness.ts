// Single source of truth for readiness scoring matrix v2 types (backend: ReadinessRuleSet / ReadinessCriterion / ReadinessScorer).
// All weights, thresholds and bands are entered by Beacon from the admin screen; no defaults live in code.

export type CriterionType = 'boolean' | 'numeric_bands' | 'choice' | 'target_ratio' | 'manual_scale';

export type MissingDataTreatment = 'counts_as_unmet' | 'excluded' | 'needs_review';

export type ResultOutcome = 'met' | 'not_met' | 'missing' | 'needs_review';

export interface NumericBand {
    min: number | null;
    max: number | null;
    points: number;
}

export interface ChoiceOption {
    value: string;
    label_ar: string;
    points: number;
}

export type CriterionConfig =
    | { type: 'boolean'; config: null }
    | { type: 'numeric_bands'; config: { unit: string; bands: NumericBand[] } }
    | { type: 'choice'; config: { options: ChoiceOption[] } }
    | { type: 'target_ratio'; config: { target: number; direction: 'higher' | 'lower'; cap: number } }
    | { type: 'manual_scale'; config: { min: number; max: number; labels_ar?: Record<string, string> } };

interface CriterionBase {
    id: number;
    readiness_rule_set_id: number;
    readiness_pillar_id: number | null;
    code: string;
    label_ar: string;
    description_ar: string | null;
    /** Weight within its pillar (decimal string from the API). */
    weight: string | number;
    is_mandatory: boolean;
    missing_data_treatment: MissingDataTreatment;
    compliance_requirement_id: number | null;
    sort_order: number;
}

export type Criterion = CriterionBase & CriterionConfig;

export interface Pillar {
    id: number;
    readiness_rule_set_id: number;
    code: string;
    label_ar: string;
    /** null or 0 = no admin weight: equal share of the score. */
    weight: string | number | null;
    min_score: string | number | null;
    sort_order: number;
    criteria?: Criterion[];
}

export interface ScoreBand {
    id: number;
    readiness_rule_set_id: number;
    label_ar: string;
    min: string | number;
    max: string | number;
    /** Design-system (#13) color token. */
    color_token: string;
    sort_order: number;
}

export interface CriterionBreakdown {
    code: string;
    label_ar: string;
    type: CriterionType;
    value: unknown;
    /** 0..1, null when excluded / needs review. */
    points: number | null;
    weight: number;
    /** Points added to the overall score (0..100 scale). */
    contribution: number;
    /** Reported outcome (before the missing-data treatment is applied). */
    outcome: ResultOutcome;
}

export interface PillarBreakdown {
    /** '_default' for the implicit pillar holding pillar-less criteria (its label_ar is null). */
    code: string;
    label_ar: string | null;
    weight: number;
    score: number | null;
    min_score: number | null;
    blocking: boolean;
    contribution: number;
    criteria: CriterionBreakdown[];
}

export interface ScoreImprovement {
    code: string;
    label_ar: string;
    potential_gain: number;
}

export interface ScoreBreakdown {
    score: number | null;
    /** `name` mirrors label_ar (stored in the snapshot band column). */
    band: (Pick<ScoreBand, 'label_ar' | 'min' | 'max' | 'color_token'> & { name?: string }) | null;
    /** null while a mandatory criterion still needs review. */
    mandatory_conditions_met: boolean | null;
    blocking: string[];
    pillars: PillarBreakdown[];
    /** Same array as `pillars`. */
    breakdown: PillarBreakdown[];
    improvements: ScoreImprovement[];
    earned_weight: number;
    counted_weight: number;
    unmet_mandatory: string[];
    needs_review: string[];
    missing: string[];
    unmet: string[];
}

export interface PreviewAnswer {
    criterion_id: number;
    outcome?: ResultOutcome | null;
    value?: unknown;
}

/** POST /admin/readiness/{ruleSet}/preview response. */
export interface PreviewResponse {
    evaluation: ScoreBreakdown;
}
