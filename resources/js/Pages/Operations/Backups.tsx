import { router } from "@inertiajs/react";
import { useMemo } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DataTable, type Column } from "../../Components/Data/DataTable";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type Run = {
    id: number;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    backup_run_id: number | null;
    size_bytes: number | null;
    error: string | null;
    triggered_by: string;
    finished_at: string | null;
    pruned: boolean;
};
type Props = { config: { enabled: boolean; time: string; retention_days: number | null; separate_key: boolean }; runs: Run[] };

const tone = (r: Run) => (r.status === "succeeded" ? "success" : r.status === "failed" ? "danger" : "warning");

function useBackupColumns(t: ReturnType<typeof useT>): Column<Run>[] {
    return useMemo(
        () => [
            { key: "id", header: "#", cell: (r) => <bdi dir="ltr">#{r.id}</bdi>, numeric: true, sortable: true },
            {
                key: "type_label",
                header: t("transactions.type", undefined, "النوع"),
                cell: (r) =>
                    `${r.type_label}${r.backup_run_id ? ` ${t("operations.backups.for_run", { id: r.backup_run_id }, `للنسخة #${r.backup_run_id}`)}` : ""}`,
                primary: true,
                sortable: true,
            },
            {
                key: "status_label",
                header: t("common.status", undefined, "الحالة"),
                cell: (r) => (
                    <StatusBadge
                        label={r.pruned ? t("operations.backups.pruned", undefined, "محذوفة حسب مدة الاحتفاظ") : r.status_label}
                        tone={tone(r)}
                    />
                ),
                sortable: true,
            },
            {
                key: "finished_at",
                header: t("operations.backups.finished_at", undefined, "الانتهاء"),
                cell: (r) => <bdi dir="ltr">{r.finished_at ?? "—"}</bdi>,
                numeric: true,
                sortable: true,
            },
            { key: "triggered_by", header: t("operations.backups.triggered_by", undefined, "بواسطة"), cell: (r) => r.triggered_by, hideOnMobile: true },
            {
                key: "size_bytes",
                header: t("operations.backups.size", undefined, "الحجم"),
                cell: (r) => (r.size_bytes ? <bdi dir="ltr">{Math.round(r.size_bytes / 1024)} KB</bdi> : "—"),
                numeric: true,
                sortable: true,
                hideOnMobile: true,
            },
            {
                key: "error",
                header: t("common.error", undefined, "الخطأ"),
                cell: (r) => (r.error ? <small className="text-danger">{r.error}</small> : "—"),
                hideOnMobile: true,
            },
            {
                key: "actions",
                header: "",
                hideable: false,
                cell: (r) =>
                    r.type === "backup" && r.status === "succeeded" && !r.pruned ? (
                        <button
                            type="button"
                            className="btn btn-outline-primary btn-sm"
                            style={{ minHeight: "44px" }}
                            onClick={() => router.post(`/operations/backups/${r.id}/restore-test`)}
                        >
                            {t("operations.backups.restore_test", undefined, "اختبار الاستعادة")}
                        </button>
                    ) : null,
            },
        ],
        [t],
    );
}

export default function Backups({ config, runs }: Props) {
    const t = useT();
    const columns = useBackupColumns(t);

    return (
        <AppLayout title={t("nav.operations.backups", undefined, "النسخ الاحتياطي")}>
            <div className="app-page">
                <PageHeader
                    title={t("operations.backups.page_title", undefined, "النسخ الاحتياطي والاستعادة")}
                    description={t(
                        "operations.backups.page_description",
                        undefined,
                        "نسخ مشفّرة لقاعدة البيانات والمستندات الخاصة. اختبار الاستعادة يتم على نسخة معزولة مؤقتة.",
                    )}
                />
                <SectionCard title={t("operations.backups.settings", undefined, "الإعدادات")}>
                    <p className="mb-1">
                        {t("operations.backups.daily", undefined, "النسخ اليومي")}:{" "}
                        {config.enabled
                            ? t("operations.backups.daily_enabled", { time: config.time }, `مفعّل (${config.time})`)
                            : t("operations.backups.daily_disabled", undefined, "غير مفعّل")}
                    </p>
                    <p className="mb-1">
                        {t("operations.backups.retention", undefined, "مدة الاحتفاظ")}:{" "}
                        {config.retention_days ?? t("operations.backups.retention_pending", undefined, "بانتظار قرار بيكون")}
                    </p>
                    <p className="mb-2">
                        {t("operations.backups.separate_key", undefined, "مفتاح تشفير منفصل")}:{" "}
                        {config.separate_key
                            ? t("common.yes", undefined, "نعم")
                            : t("operations.backups.app_key_fallback", undefined, "لا (يُستخدم مفتاح التطبيق)")}
                    </p>
                    <p className="small text-muted">
                        {t(
                            "operations.backups.restore_exercise_note",
                            undefined,
                            "تمرين الاستعادة الكامل في بيئة منفصلة مطلوب قبل القبول النهائي.",
                        )}
                    </p>
                    <button type="button" className="btn btn-primary" style={{ minHeight: "48px" }} onClick={() => router.post("/operations/backups")}>
                        {t("operations.backups.create_now", undefined, "إنشاء نسخة الآن")}
                    </button>
                </SectionCard>
                <div className="mt-3 d-flex flex-column gap-2">
                    <DataTable
                        caption={t("operations.backups.table_caption", undefined, "عمليات النسخ والاستعادة")}
                        columns={columns}
                        rows={runs}
                        rowKey={(r) => r.id}
                        defaultSort={{ key: "id", direction: "desc" }}
                        emptyTitle={t("operations.backups.empty", undefined, "لا توجد عمليات بعد")}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
