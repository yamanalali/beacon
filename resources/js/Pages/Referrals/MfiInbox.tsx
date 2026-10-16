import { Link, router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { DataTable, type Column } from "../../Components/Data/DataTable";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

type Props = { referrals: { public_id: string; enterprise_name: string | null; status_label: string; shared_at: string | null }[] };

const columns: Column<Props["referrals"][number]>[] = [
    { key: "enterprise_name", header: "المنشأة", cell: (r) => r.enterprise_name ?? "منشأة", primary: true, sortable: true },
    { key: "shared_at", header: "تاريخ المشاركة", cell: (r) => <bdi dir="ltr">{r.shared_at ?? "—"}</bdi>, numeric: true, sortable: true },
    { key: "status_label", header: "الحالة", cell: (r) => <StatusBadge label={r.status_label} tone="neutral" />, sortable: true },
    { key: "actions", header: "", hideable: false, hideOnMobile: true, cell: (r) => <Link href={`/referrals/${r.public_id}`} className="btn btn-outline-primary btn-sm" style={{ minHeight: "40px" }} onClick={(e) => e.stopPropagation()}>التفاصيل</Link> },
];

export default function MfiInbox({ referrals }: Props) {
    return (
        <AppLayout title="الإحالات الواردة">
            <div className="app-page">
                <PageHeader title="الإحالات المشاركة مع مؤسستكم" description="تظهر هنا فقط الإحالات التي وافقت المنشأة على مشاركتها معكم." />
                <div className="d-flex flex-column gap-3">
                    <DataTable
                        caption="الإحالات"
                        columns={columns}
                        rows={referrals}
                        rowKey={(r) => r.public_id}
                        rowLabel={(r) => `إحالة ${r.enterprise_name ?? ""}`}
                        onRowClick={(r) => router.visit(`/referrals/${r.public_id}`)}
                        searchable
                        emptyTitle="لا توجد إحالات"
                    />
                </div>
            </div>
        </AppLayout>
    );
}
