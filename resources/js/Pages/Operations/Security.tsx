import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";

type Item = { key: string; label_ar: string; status: "ok" | "warning" | "pending_decision"; detail_ar: string };

const tone: Record<Item["status"], StatusTone> = { ok: "success", warning: "danger", pending_decision: "warning" };
const label: Record<Item["status"], string> = { ok: "مضبوط", warning: "يحتاج ضبط", pending_decision: "بانتظار قرار بيكون" };

export default function Security({ items }: { items: Item[] }) {
    return (
        <AppLayout title="الأمان وحماية البيانات">
            <div className="app-page">
                <PageHeader title="قائمة فحص الأمان وحماية البيانات" description="حالة الوظائف الإلزامية في هذه البيئة. لا تُعرض أي قيم سرية." />
                <div className="d-flex flex-column gap-2">
                    {items.map((item) => (
                        <article key={item.key} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <strong>{item.label_ar}</strong>
                                <StatusBadge label={label[item.status]} tone={tone[item.status]} />
                            </div>
                            <p className="mb-0 text-muted">{item.detail_ar}</p>
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
