import { useState } from "react";
import { router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

type Result = {
    id: number;
    criterion: { code: string; label_ar: string; weight: number; is_mandatory: boolean; missing_data_treatment_label: string };
    compliance_version_number: number | null;
    outcome: string;
    outcome_label: string;
    note: string | null;
    action_needed: string | null;
    responsible_user_id: number | null;
    responsible_name: string | null;
    target_date: string | null;
};

import type { ReadinessScoreSnapshot } from "../../types/readiness-snapshot";

type Props = {
    snapshot?: ReadinessScoreSnapshot | null;
    snapshotHistory?: ReadinessScoreSnapshot[];
    canRecalculate?: boolean;
    enterprise: { public_id: string; business_name: string };
    assessment: { public_id: string; status: string; rule_set: { version_number: number; name_ar: string }; finalized_at: string | null };
    evaluation: { score: number | null; mandatory_conditions_met: boolean | null; earned_weight: number; counted_weight: number; unmet_mandatory: string[]; needs_review: string[]; missing: string[] };
    results: Result[];
    outcomes: Record<string, string>;
    people: { id: number; name: string }[];
    canEdit: boolean;
    disclaimer: string;
};

function ResultRow({ result, assessmentId, outcomes, people, canEdit }: { result: Result; assessmentId: string; outcomes: Record<string, string>; people: Props["people"]; canEdit: boolean }) {
    const [data, setData] = useState({
        outcome: result.outcome,
        action_needed: result.action_needed ?? "",
        responsible_user_id: result.responsible_user_id ? String(result.responsible_user_id) : "",
        target_date: result.target_date ?? "",
    });

    return (
        <article className="border rounded p-3 bg-white">
            <div className="d-flex justify-content-between flex-wrap gap-2">
                <strong>{result.criterion.label_ar}{result.criterion.is_mandatory ? " (إلزامي)" : ""}</strong>
                <StatusBadge label={result.outcome_label} tone={result.outcome === "met" ? "success" : "warning"} />
            </div>
            <small className="d-block text-muted">
                الوزن {result.criterion.weight} - البيانات الناقصة: {result.criterion.missing_data_treatment_label}
                {result.compliance_version_number ? ` - نسخة المتطلب ${result.compliance_version_number}` : ""}
            </small>
            {!canEdit ? (
                <>
                    {result.action_needed ? <p className="mb-0">الإجراء: {result.action_needed}</p> : null}
                    {result.responsible_name ? <p className="mb-0">المسؤول: {result.responsible_name}</p> : null}
                    {result.target_date ? <p className="mb-0">الموعد: {result.target_date}</p> : null}
                </>
            ) : (
                <div className="d-flex flex-column gap-2 mt-2">
                    <select aria-label="النتيجة" className="form-select" style={{ minHeight: "44px" }} value={data.outcome} onChange={(e) => setData({ ...data, outcome: e.target.value })}>
                        {Object.entries(outcomes).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                    </select>
                    <input aria-label="الإجراء المطلوب" className="form-control" style={{ minHeight: "44px" }} placeholder="الإجراء المطلوب" value={data.action_needed} onChange={(e) => setData({ ...data, action_needed: e.target.value })} />
                    <select aria-label="المسؤول" className="form-select" style={{ minHeight: "44px" }} value={data.responsible_user_id} onChange={(e) => setData({ ...data, responsible_user_id: e.target.value })}>
                        <option value="">بدون مسؤول</option>
                        {people.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                    <input aria-label="الموعد المستهدف" type="date" className="form-control" style={{ minHeight: "44px" }} value={data.target_date} onChange={(e) => setData({ ...data, target_date: e.target.value })} />
                    <button
                        type="button"
                        className="btn btn-outline-primary"
                        style={{ minHeight: "44px" }}
                        onClick={() =>
                            router.put(`/readiness-assessments/${assessmentId}/results/${result.id}`, {
                                ...data,
                                responsible_user_id: data.responsible_user_id || null,
                                target_date: data.target_date || null,
                            }, { preserveScroll: true })
                        }
                    >
                        حفظ
                    </button>
                </div>
            )}
        </article>
    );
}

export default function Assessment({ enterprise, assessment, evaluation, results, outcomes, people, canEdit, disclaimer }: Props) {
    return (
        <AppLayout title="تقييم الجاهزية">
            <div className="app-page">
                <PageHeader title="تقييم الجاهزية" description={`${enterprise.business_name} - ${assessment.rule_set.name_ar} (نسخة ${assessment.rule_set.version_number})`} />
                <div className="alert alert-info">{disclaimer}</div>
                <SectionCard title="أساس التقييم">
                    <p className="mb-1">النتيجة: {evaluation.score !== null ? `${evaluation.score} من 100` : "غير محسوبة"}</p>
                    <p className="mb-1">الأوزان المستوفاة {evaluation.earned_weight} من أصل {evaluation.counted_weight} محسوبة.</p>
                    <p className="mb-1">الشروط الإلزامية: {evaluation.mandatory_conditions_met === null ? "تحتاج مراجعة" : evaluation.mandatory_conditions_met ? "مستوفاة" : "غير مستوفاة"}</p>
                    {evaluation.unmet_mandatory.length ? <p className="mb-1 text-danger">إلزامية غير مستوفاة: {evaluation.unmet_mandatory.join("، ")}</p> : null}
                    {evaluation.missing.length ? <p className="mb-1">بيانات ناقصة: {evaluation.missing.join("، ")}</p> : null}
                    {evaluation.needs_review.length ? <p className="mb-0">تحتاج مراجعة: {evaluation.needs_review.join("، ")}</p> : null}
                </SectionCard>
                <div className="mt-3 d-flex flex-column gap-3">
                    {results.map((r) => <ResultRow key={r.id} result={r} assessmentId={assessment.public_id} outcomes={outcomes} people={people} canEdit={canEdit} />)}
                </div>
                {canEdit ? (
                    <button type="button" className="btn btn-primary mt-3" style={{ minHeight: "48px" }} onClick={() => router.post(`/readiness-assessments/${assessment.public_id}/finalize`)}>
                        اعتماد التقييم كنهائي
                    </button>
                ) : null}
            </div>
        </AppLayout>
    );
}
