import type { Criterion, Pillar, ScoreBand } from "../../types/readiness";
import type { RuleSetDraft } from "./types";

// Mock data for tests only. Numbers are arbitrary and NOT business values.
const base = { readiness_rule_set_id: 1, description_ar: null, compliance_requirement_id: null } as const;
export const pillars: Pillar[] = [
    { id: 10, readiness_rule_set_id: 1, code: "A", label_ar: "محور أ", weight: "2", min_score: "50", sort_order: 0 },
    { id: 20, readiness_rule_set_id: 1, code: "B", label_ar: "محور ب", weight: 1, min_score: null, sort_order: 1 },
];
export const criteria: Criterion[] = [
    { ...base, id: 1, readiness_pillar_id: 10, code: "a1", label_ar: "سجل تجاري", weight: "1", is_mandatory: true, missing_data_treatment: "counts_as_unmet", sort_order: 0, type: "boolean", config: null },
    { ...base, id: 2, readiness_pillar_id: 10, code: "a2", label_ar: "أشهر السجلات", weight: 1, is_mandatory: false, missing_data_treatment: "counts_as_unmet", sort_order: 1, type: "numeric_bands", config: { unit: "شهر", bands: [{ min: 0, max: 9, points: 0 }, { min: 10, max: null, points: 1 }] } },
    { ...base, id: 3, readiness_pillar_id: 20, code: "b1", label_ar: "نوع الحسابات", weight: 1, is_mandatory: false, missing_data_treatment: "excluded", sort_order: 0, type: "choice", config: { options: [{ value: "x", label_ar: "كاملة", points: 1 }, { value: "y", label_ar: "جزئية", points: 0.5 }] } },
    { ...base, id: 4, readiness_pillar_id: 20, code: "b2", label_ar: "نسبة التحصيل", weight: 1, is_mandatory: false, missing_data_treatment: "excluded", sort_order: 1, type: "target_ratio", config: { target: 100, direction: "higher", cap: 1 } },
    { ...base, id: 5, readiness_pillar_id: null, code: "c1", label_ar: "تقييم المستشار", weight: 1, is_mandatory: false, missing_data_treatment: "needs_review", sort_order: 0, type: "manual_scale", config: { min: 1, max: 5, labels_ar: { "1": "ضعيف", "5": "ممتاز" } } },
];
export const bands: ScoreBand[] = [
    { id: 1, readiness_rule_set_id: 1, label_ar: "منخفضة", min: 0, max: 50, color_token: "danger", sort_order: 0 },
    { id: 2, readiness_rule_set_id: 1, label_ar: "متوسطة", min: "50", max: "80", color_token: "warning", sort_order: 1 },
    { id: 3, readiness_rule_set_id: 1, label_ar: "مرتفعة", min: 80, max: 100, color_token: "success", sort_order: 2 },
];
export const draft: RuleSetDraft = { id: 1, version_number: 3, name_ar: "قواعد تجريبية", status: "draft", change_summary: null, pillars, criteria, bands };
