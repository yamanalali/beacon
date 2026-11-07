import { Link, router } from "@inertiajs/react";
import { useMemo } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { DataTable, type Column } from "../../Components/Data/DataTable";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type ReferralRow = { public_id: string; enterprise_name: string | null; status_label: string; shared_at: string | null };
type Props = { referrals: ReferralRow[] };

function useMfiInboxColumns(t: ReturnType<typeof useT>): Column<ReferralRow>[] {
    return useMemo(
        () => [
            {
                key: "enterprise_name",
                header: t("referrals.column.enterprise", undefined, "المنشأة"),
                cell: (r) => r.enterprise_name ?? t("referrals.enterprise_fallback", undefined, "منشأة"),
                primary: true,
                sortable: true,
            },
            {
                key: "shared_at",
                header: t("referrals.column.shared_at", undefined, "تاريخ المشاركة"),
                cell: (r) => <bdi dir="ltr">{r.shared_at ?? "—"}</bdi>,
                numeric: true,
                sortable: true,
            },
            {
                key: "status_label",
                header: t("common.status", undefined, "الحالة"),
                cell: (r) => <StatusBadge label={r.status_label} tone="neutral" />,
                sortable: true,
            },
            {
                key: "actions",
                header: "",
                hideable: false,
                hideOnMobile: true,
                cell: (r) => (
                    <Link
                        href={`/referrals/${r.public_id}`}
                        className="btn btn-outline-primary btn-sm"
                        style={{ minHeight: "40px" }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        {t("common.details", undefined, "التفاصيل")}
                    </Link>
                ),
            },
        ],
        [t],
    );
}

export default function MfiInbox({ referrals }: Props) {
    const t = useT();
    const columns = useMfiInboxColumns(t);

    return (
        <AppLayout title={t("nav.mfi.referrals", undefined, "الإحالات الواردة")}>
            <div className="app-page">
                <PageHeader
                    title={t("referrals.mfi_inbox_title", undefined, "الإحالات المشاركة مع مؤسستكم")}
                    description={t(
                        "referrals.mfi_inbox_description",
                        undefined,
                        "تظهر هنا فقط الإحالات التي وافقت المنشأة على مشاركتها معكم.",
                    )}
                />
                <div className="d-flex flex-column gap-3">
                    <DataTable
                        caption={t("referrals.title", undefined, "الإحالات")}
                        columns={columns}
                        rows={referrals}
                        rowKey={(r) => r.public_id}
                        rowLabel={(r) => `${t("referrals.referral", undefined, "إحالة")} ${r.enterprise_name ?? ""}`}
                        onRowClick={(r) => router.visit(`/referrals/${r.public_id}`)}
                        searchable
                        emptyTitle={t("referrals.empty", undefined, "لا توجد إحالات")}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
