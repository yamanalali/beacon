import { useState } from "react";
import { router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";

type Item = {
    requirement_public_id: string;
    category_label: string;
    version: { version_number: number; title_ar: string; guidance_ar: string | null; guidance_pending: boolean; deadline_note_ar: string | null };
    status: string;
    status_label: string;
    due_date: string | null;
    notes: string | null;
    missing_document: boolean;
    overdue: boolean;
    approaching: boolean;
    action_needed: boolean;
};

type Props = {
    enterprise: { public_id: string; business_name: string };
    items: Item[];
    statuses: Record<string, string>;
    canUpdate: boolean;
};

function tone(item: Item): StatusTone {
    if (item.status === "completed") return "success";
    if (item.overdue) return "danger";
    return "warning";
}

function Row({ item, enterprise, statuses, canUpdate }: { item: Item; enterprise: Props["enterprise"]; statuses: Record<string, string>; canUpdate: boolean }) {
    const [status, setStatus] = useState(item.status);
    const [dueDate, setDueDate] = useState(item.due_date ?? "");
    const id = item.requirement_public_id;

    return (
        <article className="app-mobile-record" data-testid="compliance-item">
            <div className="fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>{item.version.title_ar}</span>
                <span className="d-flex flex-wrap gap-2">
                    {item.action_needed ? <StatusBadge label="إجراء مطلوب" tone="danger" /> : null}
                    <StatusBadge label={item.status_label} tone={tone(item)} />
                </span>
            </div>
            <p className="app-record-line"><span>التصنيف</span><span>{item.category_label} - نسخة {item.version.version_number}</span></p>
            <p className="app-record-line"><span>الإرشاد</span><span>{item.version.guidance_pending ? "الإرشاد بانتظار مدخلات بيكون" : item.version.guidance_ar ?? "-"}</span></p>
            {item.version.deadline_note_ar ? <p className="app-record-line"><span>ملاحظة الموعد</span><span>{item.version.deadline_note_ar}</span></p> : null}
            {item.due_date ? (
                <p className="app-record-line">
                    <span>الموعد</span>
                    <span><bdi dir="ltr">{item.due_date}</bdi>{item.overdue ? " (متأخر)" : item.approaching ? " (قريب)" : ""}</span>
                </p>
            ) : null}
            {item.missing_document ? <p className="app-record-line"><span>الوثائق</span><span className="text-danger">وثيقة مطلوبة لم تُستكمل</span></p> : null}
            {canUpdate ? (
                <div className="row g-2 align-items-end pt-3">
                    <div className="col-12 col-md-5">
                        <label className="form-label" htmlFor={`status-${id}`}>الحالة</label>
                        <select id={`status-${id}`} className="form-select" value={status} onChange={(e) => setStatus(e.target.value)}>
                            {Object.entries(statuses).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                        </select>
                    </div>
                    <div className="col-12 col-md-4">
                        <label className="form-label" htmlFor={`due-${id}`}>الموعد</label>
                        <input id={`due-${id}`} type="date" className="form-control" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
                    </div>
                    <div className="col-12 col-md-3 d-grid">
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => router.put(`/enterprises/${enterprise.public_id}/compliance/${id}`, { status, due_date: dueDate || null }, { preserveScroll: true })}
                        >
                            حفظ
                        </button>
                    </div>
                </div>
            ) : null}
        </article>
    );
}

export default function Enterprise({ enterprise, items, statuses, canUpdate }: Props) {
    const actionCount = items.filter((i) => i.action_needed).length;
    const doneCount = items.filter((i) => i.status === "completed").length;

    return (
        <AppLayout title="الامتثال">
            <div className="app-page">
                <PageHeader title="الامتثال" description={`${enterprise.business_name} - المتطلبات المطبقة وحالتها.`} />
                {items.length > 0 ? (
                    <div className="app-page-toolbar" data-testid="compliance-summary">
                        <span className="d-flex flex-wrap gap-2">
                            <StatusBadge label={`${doneCount} من ${items.length} مكتمل`} tone="success" />
                            {actionCount > 0 ? <StatusBadge label={`${actionCount} يحتاج إجراء`} tone="danger" /> : null}
                        </span>
                    </div>
                ) : null}
                <SectionCard title="المتطلبات">
                    {items.length === 0 ? (
                        <EmptyState icon="bi-shield-check" title="لا توجد متطلبات منشورة بعد" description="ستظهر هنا المتطلبات التي تنطبق على منشأتك عند نشرها من بيكون." />
                    ) : (
                        <div className="d-flex flex-column gap-3">
                            {items.map((item) => (
                                <Row key={item.requirement_public_id} item={item} enterprise={enterprise} statuses={statuses} canUpdate={canUpdate} />
                            ))}
                        </div>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}