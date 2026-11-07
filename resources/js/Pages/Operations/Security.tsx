import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type Item = { key: string; label_ar: string; status: "ok" | "warning" | "pending_decision"; detail_ar: string };

const tone: Record<Item["status"], StatusTone> = { ok: "success", warning: "danger", pending_decision: "warning" };

export default function Security({ items }: { items: Item[] }) {
    const t = useT();
    const statusLabel: Record<Item["status"], string> = {
        ok: t("operations.security.status.ok", undefined, "مضبوط"),
        warning: t("operations.security.status.warning", undefined, "يحتاج ضبط"),
        pending_decision: t("operations.security.status.pending", undefined, "بانتظار قرار بيكون"),
    };

    return (
        <AppLayout title={t("operations.security.title", undefined, "الأمان وحماية البيانات")}>
            <div className="app-page">
                <PageHeader
                    title={t("operations.security.page_title", undefined, "قائمة فحص الأمان وحماية البيانات")}
                    description={t(
                        "operations.security.page_description",
                        undefined,
                        "حالة الوظائف الإلزامية في هذه البيئة. لا تُعرض أي قيم سرية.",
                    )}
                />
                <div className="d-flex flex-column gap-2">
                    {items.map((item) => (
                        <article key={item.key} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <strong>{item.label_ar}</strong>
                                <StatusBadge label={statusLabel[item.status]} tone={tone[item.status]} />
                            </div>
                            <p className="mb-0 text-muted">{item.detail_ar}</p>
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
