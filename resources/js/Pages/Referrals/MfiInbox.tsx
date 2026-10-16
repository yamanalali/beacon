import { Link } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

type Props = { referrals: { public_id: string; enterprise_name: string | null; status_label: string; shared_at: string | null }[] };

export default function MfiInbox({ referrals }: Props) {
    return (
        <AppLayout title="الإحالات الواردة">
            <div className="app-page">
                <PageHeader title="الإحالات المشاركة مع مؤسستكم" description="تظهر هنا فقط الإحالات التي وافقت المنشأة على مشاركتها معكم." />
                <div className="d-flex flex-column gap-3">
                    {referrals.length === 0 ? <EmptyState title="لا توجد إحالات" /> : null}
                    {referrals.map((r) => (
                        <Link key={r.public_id} href={`/referrals/${r.public_id}`} className="border rounded p-3 bg-white text-decoration-none text-body d-flex justify-content-between flex-wrap gap-2">
                            <span>{r.enterprise_name ?? "منشأة"} - {r.shared_at}</span>
                            <StatusBadge label={r.status_label} tone="neutral" />
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
