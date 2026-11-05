import { Link, usePage } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EscalationStatus, shortDate } from "../../../Components/Ai/aiShared";

type Props = { escalation: { id: string; question: string; status: string; created_at: string | null; enterprise_id?: string | null } };

const STAFF = new Set(["beacon_advisor", "beacon_admin"]);

export default function Show({ escalation }: Props) {
    const role = usePage().props.auth.user?.role ?? "";
    const staff = STAFF.has(role);

    return (
        <AppLayout title="تفاصيل الاستفسار">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title="تفاصيل الاستفسار"
                    description={staff ? "سؤال وصل إلى فريق بيكون ويحتاج مراجعة بشرية." : "سؤال أرسلته إلى فريق بيكون."}
                />
                <div>
                    <Link href="/ai/pages/escalations" className="btn btn-link px-0">
                        <i className="bi bi-arrow-right me-1" aria-hidden="true" />
                        العودة إلى الاستفسارات
                    </Link>
                </div>

                <div className="row g-3 align-items-start">
                    <div className="col-12 col-lg-8">
                        <SectionCard title="السؤال">
                            <p className="mb-0" style={{ whiteSpace: "pre-line", fontSize: "1.05rem" }}>
                                {escalation.question}
                            </p>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-4 d-flex flex-column gap-3">
                        <SectionCard title="المتابعة">
                            <dl className="row mb-0 small">
                                <dt className="col-5 text-body-secondary fw-normal">الحالة</dt>
                                <dd className="col-7">
                                    <EscalationStatus status={escalation.status} />
                                </dd>
                                <dt className="col-5 text-body-secondary fw-normal">تاريخ الإرسال</dt>
                                <dd className="col-7">
                                    <bdi dir="ltr">{shortDate(escalation.created_at)}</bdi>
                                </dd>
                                {escalation.enterprise_id ? (
                                    <>
                                        <dt className="col-5 text-body-secondary fw-normal">المنشأة</dt>
                                        <dd className="col-7 mb-0">
                                            <Link href={`/enterprises/${escalation.enterprise_id}`}>عرض المنشأة</Link>
                                        </dd>
                                    </>
                                ) : null}
                            </dl>
                        </SectionCard>
                        {staff ? (
                            <SectionCard title="رد الموظف">
                                <p className="mb-2">هذا هو مكان مراجعة السؤال. يصل المستشار ومدير بيكون إليه من قائمة الاستفسارات.</p>
                                <p className="small text-body-secondary mb-0">
                                    حفظ رد مكتوب داخل المنصة غير مفعّل بعد. بانتظار قرار بيكون: من يستلم الاستفسار، حالات المتابعة، وهل يصل الرد للمستخدم داخل التطبيق.
                                </p>
                            </SectionCard>
                        ) : (
                            <SectionCard title="الرد">
                                <p className="small text-body-secondary mb-0">سيتواصل معك فريق بيكون عبر الإشعارات عند توفر الرد.</p>
                            </SectionCard>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
