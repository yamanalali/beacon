import { Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type Assessment = {
    public_id: string;
    status: string;
    score: string | null;
    mandatory_conditions_met: boolean | null;
    rule_set: string;
    created_at: string | null;
    finalized_at: string | null;
};
type Props = { enterprise: { public_id: string; business_name: string }; assessments: Assessment[]; canAssess: boolean; hasPublishedRules: boolean };

export default function Enterprise({ enterprise, assessments, canAssess, hasPublishedRules }: Props) {
    const t = useT();
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const start = () => router.post(`/enterprises/${enterprise.public_id}/readiness`);
    const canStart = canAssess && hasPublishedRules;

    return (
        <AppLayout title={t("readiness.title", undefined, "الجاهزية")}>
            <div className="app-page">
                <PageHeader
                    title={t("readiness.enterprise.page_title", undefined, "تقييم الجاهزية المالية")}
                    description={t(
                        "readiness.enterprise.page_description",
                        { name: enterprise.business_name },
                        `${enterprise.business_name} - النتيجة استشارية وليست موافقة تمويل.`,
                    )}
                />
                {canAssess ? (
                    <div className="app-page-toolbar">
                        <p className="mb-0 text-muted">
                            {hasPublishedRules
                                ? t("readiness.enterprise.start_hint", undefined, "ابدأ تقييماً جديداً بناءً على أحدث القواعد المعتمدة.")
                                : t("readiness.enterprise.no_rules", undefined, "لا توجد قواعد معتمدة من بيكون بعد.")}
                        </p>
                        <button type="button" className="btn btn-primary" disabled={!hasPublishedRules} onClick={start}>
                            <i className="bi bi-plus-lg" aria-hidden="true" /> {t("readiness.new_assessment", undefined, "تقييم جديد")}
                        </button>
                    </div>
                ) : null}
                {errors.rule_set ? (
                    <div className="alert alert-danger" role="alert">
                        {errors.rule_set}
                    </div>
                ) : null}
                <SectionCard title={t("readiness.assessments_section", undefined, "التقييمات")}>
                    {assessments.length === 0 ? (
                        <EmptyState
                            icon="bi-clipboard-check"
                            title={t("readiness.empty_assessments", undefined, "لا توجد تقييمات بعد")}
                            description={
                                canStart
                                    ? t(
                                          "readiness.empty_assessments_desc",
                                          undefined,
                                          "أجرِ أول تقييم لمعرفة جاهزية منشأتك للتمويل.",
                                      )
                                    : undefined
                            }
                            actionLabel={canStart ? t("readiness.new_assessment", undefined, "تقييم جديد") : undefined}
                            onAction={canStart ? start : undefined}
                        />
                    ) : (
                        <ul className="list-unstyled d-flex flex-column gap-3 mb-0" data-testid="assessments">
                            {assessments.map((a) => (
                                <li key={a.public_id}>
                                    <Link
                                        href={`/readiness-assessments/${a.public_id}`}
                                        className="app-mobile-record d-block text-decoration-none text-body"
                                    >
                                        <div className="fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <span>{a.rule_set}</span>
                                            <StatusBadge
                                                label={
                                                    a.status === "finalized"
                                                        ? t("readiness.status.finalized", undefined, "نهائي")
                                                        : t("status.draft", undefined, "مسودة")
                                                }
                                                tone={a.status === "finalized" ? "success" : "warning"}
                                            />
                                        </div>
                                        <p className="app-record-line">
                                            <span>{t("readiness.score_label", undefined, "النتيجة")}</span>
                                            <span>
                                                {a.score !== null ? (
                                                    <>
                                                        <bdi dir="ltr">{a.score}</bdi> {t("readiness.score_of_100", undefined, "من 100")}
                                                    </>
                                                ) : (
                                                    t("readiness.score_not_calculated", undefined, "لم تُحسب النتيجة")
                                                )}
                                            </span>
                                        </p>
                                        {a.mandatory_conditions_met !== null ? (
                                            <p className="app-record-line">
                                                <span>{t("readiness.mandatory_conditions", undefined, "الشروط الإلزامية")}</span>
                                                <span>
                                                    {a.mandatory_conditions_met
                                                        ? t("readiness.mandatory_met", undefined, "مستوفاة")
                                                        : t("readiness.mandatory_not_met", undefined, "غير مستوفاة")}
                                                </span>
                                            </p>
                                        ) : null}
                                        <p className="app-record-line">
                                            <span>
                                                {a.finalized_at
                                                    ? t("readiness.finalized_at", undefined, "تاريخ الاعتماد")
                                                    : t("readiness.created_at", undefined, "تاريخ الإنشاء")}
                                            </span>
                                            <span>
                                                <bdi dir="ltr">{a.finalized_at ?? a.created_at ?? "-"}</bdi>
                                            </span>
                                        </p>
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
