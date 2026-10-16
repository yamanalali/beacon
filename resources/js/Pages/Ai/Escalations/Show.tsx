import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { StatusBadge } from "../../../Components/Feedback/StatusBadge";
import { displayDate, statusLabels, type Escalation } from "../shared";

export default function Show({ escalation }: { escalation: Escalation }) {
    return (
        <AppLayout title="تفاصيل الاستفسار">
            <div className="app-page">
                <PageHeader
                    title="تفاصيل الاستفسار"
                    description="السؤال المرسل إلى فريق بيكون وحالته الحالية."
                />
                <Link
                    href="/ai/pages/escalations"
                    className="btn btn-light mb-3"
                >
                    العودة إلى الاستفسارات
                </Link>
                <SectionCard title="السؤال">
                    <div className="d-flex flex-wrap gap-3 align-items-center mb-3">
                        <StatusBadge
                            label={
                                statusLabels[escalation.status] ??
                                escalation.status
                            }
                            tone="warning"
                        />
                        <span className="small text-body-secondary">
                            أُرسل في {displayDate(escalation.created_at)}
                        </span>
                    </div>
                    <p
                        style={{
                            whiteSpace: "pre-wrap",
                            overflowWrap: "anywhere",
                        }}
                    >
                        {escalation.question}
                    </p>
                    {escalation.enterprise_id ? (
                        <Link
                            href={`/enterprises/${encodeURIComponent(escalation.enterprise_id)}`}
                            className="btn btn-outline-primary"
                        >
                            ملف المنشأة
                        </Link>
                    ) : null}
                </SectionCard>
            </div>
        </AppLayout>
    );
}
