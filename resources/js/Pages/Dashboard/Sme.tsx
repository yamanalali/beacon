import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";

/**
 * Simple SME personal dashboard (§40). Summarizes existing information only; no new metrics or scoring.
 * STUB: each area renders an empty state until its owning module (finance/compliance/readiness/referral) exposes data.
 */
export type SummaryItem = { id: string | number; title: string; meta?: string };

type Props = {
    recentActivity?: SummaryItem[];
    outstandingActions?: SummaryItem[];
    upcomingDeadlines?: SummaryItem[];
    readinessStatus?: string | null;
    referralStatus?: string | null;
};

function List({ items, empty }: { items: SummaryItem[]; empty: string }) {
    if (items.length === 0) return <EmptyState title={empty} />;
    return (
        <ul className="list-group list-group-flush">
            {items.map((i) => (
                <li key={i.id} className="list-group-item px-0 d-flex justify-content-between gap-2">
                    <span>{i.title}</span>
                    {i.meta ? <bdi dir="ltr" className="text-body-secondary small">{i.meta}</bdi> : null}
                </li>
            ))}
        </ul>
    );
}

export default function Sme({ recentActivity = [], outstandingActions = [], upcomingDeadlines = [], readinessStatus = null, referralStatus = null }: Props) {
    return (
        <AppLayout title="الرئيسية">
            <div className="app-page">
                <PageHeader title="ملخص منشأتك" description="آخر النشاطات وما يحتاج إجراء منك." />
                <div className="row g-3">
                    <div className="col-12 col-lg-6"><SectionCard title="إجراءات مطلوبة"><List items={outstandingActions} empty="لا توجد إجراءات مطلوبة" /></SectionCard></div>
                    <div className="col-12 col-lg-6"><SectionCard title="مواعيد امتثال قريبة"><List items={upcomingDeadlines} empty="لا توجد مواعيد قريبة" /></SectionCard></div>
                    <div className="col-12 col-lg-6"><SectionCard title="آخر النشاط المالي"><List items={recentActivity} empty="لا يوجد نشاط مالي بعد" /></SectionCard></div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title="الجاهزية والإحالة">
                            <p className="mb-1">حالة الجاهزية: <strong>{readinessStatus ?? "غير متاحة بعد"}</strong></p>
                            {referralStatus ? <p className="mb-0">حالة الإحالة: <strong>{referralStatus}</strong></p> : null}
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}