import { router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
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
                    {runs.length === 0 ? <EmptyState title="لا توجد عمليات بعد" /> : null}
                    {runs.map((r) => (
                        <article key={r.id} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <strong>#{r.id} {r.type_label}{r.backup_run_id ? ` للنسخة #${r.backup_run_id}` : ""}</strong>
                                <StatusBadge label={r.pruned ? "محذوفة حسب مدة الاحتفاظ" : r.status_label} tone={r.status === "succeeded" ? "success" : r.status === "failed" ? "danger" : "warning"} />
                            </div>
                            <small className="d-block">{r.finished_at} - {r.triggered_by}{r.size_bytes ? ` - ${Math.round(r.size_bytes / 1024)} KB` : ""}</small>
                            {r.error ? <small className="d-block text-danger">{r.error}</small> : null}
                            {r.type === "backup" && r.status === "succeeded" && !r.pruned ? (
                                <button type="button" className="btn btn-outline-primary btn-sm mt-2" style={{ minHeight: "44px" }} onClick={() => router.post(`/operations/backups/${r.id}/restore-test`)}>اختبار الاستعادة</button>
                            ) : null}
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
