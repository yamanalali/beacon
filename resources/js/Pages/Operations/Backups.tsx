import { router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DataTable, type Column } from "../../Components/Data/DataTable";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

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

const columns: Column<Run>[] = [
    { key: "id", header: "#", cell: (r) => <bdi dir="ltr">#{r.id}</bdi>, numeric: true, sortable: true },
    { key: "type_label", header: "النوع", cell: (r) => `${r.type_label}${r.backup_run_id ? ` للنسخة #${r.backup_run_id}` : ""}`, primary: true, sortable: true },
    { key: "status_label", header: "الحالة", cell: (r) => <StatusBadge label={r.pruned ? "محذوفة حسب مدة الاحتفاظ" : r.status_label} tone={tone(r)} />, sortable: true },
    { key: "finished_at", header: "الانتهاء", cell: (r) => <bdi dir="ltr">{r.finished_at ?? "—"}</bdi>, numeric: true, sortable: true },
    { key: "triggered_by", header: "بواسطة", cell: (r) => r.triggered_by, hideOnMobile: true },
    { key: "size_bytes", header: "الحجم", cell: (r) => (r.size_bytes ? <bdi dir="ltr">{Math.round(r.size_bytes / 1024)} KB</bdi> : "—"), numeric: true, sortable: true, hideOnMobile: true },
    { key: "error", header: "الخطأ", cell: (r) => (r.error ? <small className="text-danger">{r.error}</small> : "—"), hideOnMobile: true },
    {
        key: "actions",
        header: "",
        hideable: false,
        cell: (r) =>
            r.type === "backup" && r.status === "succeeded" && !r.pruned ? (
                <button type="button" className="btn btn-outline-primary btn-sm" style={{ minHeight: "44px" }} onClick={() => router.post(`/operations/backups/${r.id}/restore-test`)}>اختبار الاستعادة</button>
            ) : null,
    },
];

export default function Backups({ config, runs }: Props) {
    return (
        <AppLayout title="النسخ الاحتياطي">
            <div className="app-page">
                <PageHeader title="النسخ الاحتياطي والاستعادة" description="نسخ مشفّرة لقاعدة البيانات والمستندات الخاصة. اختبار الاستعادة يتم على نسخة معزولة مؤقتة." />
                <SectionCard title="الإعدادات">
                    <p className="mb-1">النسخ اليومي: {config.enabled ? `مفعّل (${config.time})` : "غير مفعّل"}</p>
                    <p className="mb-1">مدة الاحتفاظ: {config.retention_days ?? "بانتظار قرار بيكون"}</p>
                    <p className="mb-2">مفتاح تشفير منفصل: {config.separate_key ? "نعم" : "لا (يُستخدم مفتاح التطبيق)"}</p>
                    <p className="small text-muted">تمرين الاستعادة الكامل في بيئة منفصلة مطلوب قبل القبول النهائي.</p>
                    <button type="button" className="btn btn-primary" style={{ minHeight: "48px" }} onClick={() => router.post("/operations/backups")}>إنشاء نسخة الآن</button>
                </SectionCard>
                <div className="mt-3 d-flex flex-column gap-2">
                    <DataTable caption="عمليات النسخ والاستعادة" columns={columns} rows={runs} rowKey={(r) => r.id} defaultSort={{ key: "id", direction: "desc" }} emptyTitle="لا توجد عمليات بعد" />
                </div>
            </div>
        </AppLayout>
    );
}
