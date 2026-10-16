import { Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

type Assessment = { public_id: string; status: string; score: string | null; mandatory_conditions_met: boolean | null; rule_set: string; created_at: string | null; finalized_at: string | null };
type Props = { enterprise: { public_id: string; business_name: string }; assessments: Assessment[]; canAssess: boolean; hasPublishedRules: boolean };

export default function Enterprise({ enterprise, assessments, canAssess, hasPublishedRules }: Props) {
    const errors = usePage().props.errors as Record<string, string>;

    return (
        <AppLayout title="الجاهزية">
            <div className="app-page">
                <PageHeader title="تقييم الجاهزية المالية" description={`${enterprise.business_name} - النتيجة استشارية وليست موافقة تمويل.`} />
                {canAssess ? (
                    <div className="mb-3">
                        <button type="button" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={!hasPublishedRules} onClick={() => router.post(`/enterprises/${enterprise.public_id}/readiness`)}>
                            تقييم جديد
                        </button>
                        {!hasPublishedRules ? <p className="text-muted mt-2">لا توجد قواعد معتمدة من بيكون بعد.</p> : null}
                        {errors?.rule_set ? <p className="text-danger mt-2">{errors.rule_set}</p> : null}
                    </div>
                ) : null}
                <div className="d-flex flex-column gap-3">
                    {assessments.length === 0 ? <EmptyState title="لا توجد تقييمات بعد" /> : null}
                    {assessments.map((a) => (
                        <Link key={a.public_id} href={`/readiness-assessments/${a.public_id}`} className="border rounded p-3 bg-white text-decoration-none text-body">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <strong>{a.rule_set}</strong>
                                <StatusBadge label={a.status === "finalized" ? "نهائي" : "مسودة"} tone={a.status === "finalized" ? "success" : "warning"} />
                            </div>
                            <p className="mb-0">{a.score !== null ? `النتيجة: ${a.score} من 100` : "لم تُحسب النتيجة"} - {a.finalized_at ?? a.created_at}</p>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
