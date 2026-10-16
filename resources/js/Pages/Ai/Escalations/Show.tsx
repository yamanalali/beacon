import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EscalationStatus, shortDate } from "../../../Components/Ai/aiShared";

type Props = { escalation: { id: string; question: string; status: string; created_at: string | null; enterprise_id?: string | null } };

export default function Show({ escalation }: Props) {
    return (
        <AppLayout title="تفاصيل الاستفسار">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="تفاصيل الاستفسار" description="سؤال مرسل إلى فريق بيكون." />
                <div>
                    <Link href="/ai/pages/escalations" className="btn btn-link px-0">
                        <i className="bi bi-arrow-right me-1" aria-hidden="true" />
                        العودة إلى الاستفسارات
                    </Link>
                </div>
                <SectionCard title="السؤال">
                    <p className="mb-3" style={{ whiteSpace: "pre-line" }}>
                        {escalation.question}
                    </p>
                    <dl className="row mb-0 small">
                        <dt className="col-5 col-sm-3 text-body-secondary fw-normal">الحالة</dt>
                        <dd className="col-7 col-sm-9">
                            <EscalationStatus status={escalation.status} />
                        </dd>
                        <dt className="col-5 col-sm-3 text-body-secondary fw-normal">تاريخ الإرسال</dt>
                        <dd className="col-7 col-sm-9">
                            <bdi dir="ltr">{shortDate(escalation.created_at)}</bdi>
                        </dd>
                        {escalation.enterprise_id ? (
                            <>
                                <dt className="col-5 col-sm-3 text-body-secondary fw-normal">المنشأة</dt>
                                <dd className="col-7 col-sm-9 mb-0">
                                    <Link href={`/enterprises/${escalation.enterprise_id}`}>عرض المنشأة</Link>
                                </dd>
                            </>
                        ) : null}
                    </dl>
                </SectionCard>
                <p className="small text-body-secondary mb-0">سيتواصل معك فريق بيكون عبر الإشعارات عند الرد.</p>
            </div>
        </AppLayout>
    );
}
