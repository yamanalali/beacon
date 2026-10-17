import { Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

type Assessment = { public_id: string; status: string; score: string | null; mandatory_conditions_met: boolean | null; rule_set: string; created_at: string | null; finalized_at: string | null };
type Props = { enterprise: { public_id: string; business_name: string }; assessments: Assessment[]; canAssess: boolean; hasPublishedRules: boolean };

export default function Enterprise({ enterprise, assessments, canAssess, hasPublishedRules }: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const start = () => router.post(`/enterprises/${enterprise.public_id}/readiness`);
    const canStart = canAssess && hasPublishedRules;

    return (
        <AppLayout title="الجاهزية">
            <div className="app-page">
                <PageHeader title="تقييم الجاهزية المالية" description={`${enterprise.business_name} - النتيجة استشارية وليست موافقة تمويل.`} />
                {canAssess ? (
                    <div className="app-page-toolbar">
                        <p className="mb-0 text-muted">{hasPublishedRules ? "ابدأ تقييماً جديداً بناءً على أحدث القواعد المعتمدة." : "لا توجد قواعد معتمدة من بيكون بعد."}</p>
                        <button type="button" className="btn btn-primary" disabled={!hasPublishedRules} onClick={start}>
                            <i className="bi bi-plus-lg" aria-hidden="true" /> تقييم جديد
                        </button>
                    </div>
                ) : null}
                {errors.rule_set ? <div className="alert alert-danger" role="alert">{errors.rule_set}</div> : null}
                <SectionCard title="التقييمات">
                    {assessments.length === 0 ? (
                        <EmptyState
                            icon="bi-clipboard-check"
                            title="لا توجد تقييمات بعد"
                            description={canStart ? "أجرِ أول تقييم لمعرفة جاهزية منشأتك للتمويل." : undefined}
                            actionLabel={canStart ? "تقييم جديد" : undefined}
                            onAction={canStart ? start : undefined}
                        />
                    ) : (
                        <ul className="list-unstyled d-flex flex-column gap-3 mb-0" data-testid="assessments">
                            {assessments.map((a) => (
                                <li key={a.public_id}>
                                    <Link href={`/readiness-assessments/${a.public_id}`} className="app-mobile-record d-block text-decoration-none text-body">
                                        <div className="fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <span>{a.rule_set}</span>
                                            <StatusBadge label={a.status === "finalized" ? "نهائي" : "مسودة"} tone={a.status === "finalized" ? "success" : "warning"} />
                                        </div>
                                        <p className="app-record-line"><span>النتيجة</span><span>{a.score !== null ? <><bdi dir="ltr">{a.score}</bdi> من 100</> : "لم تُحسب النتيجة"}</span></p>
                                        {a.mandatory_conditions_met !== null ? (
                                            <p className="app-record-line"><span>الشروط الإلزامية</span><span>{a.mandatory_conditions_met ? "مستوفاة" : "غير مستوفاة"}</span></p>
                                        ) : null}
                                        <p className="app-record-line"><span>{a.finalized_at ? "تاريخ الاعتماد" : "تاريخ الإنشاء"}</span><span><bdi dir="ltr">{a.finalized_at ?? a.created_at ?? "-"}</bdi></span></p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}