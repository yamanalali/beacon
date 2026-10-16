import { useState } from "react";
import { router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";

type Item = {
    requirement_public_id: string;
    category_label: string;
    version: {
        version_number: number;
        title_ar: string;
        guidance_ar: string | null;
        guidance_pending: boolean;
        deadline_note_ar: string | null;
    };
    needs_version_review: boolean;
    recorded_version_number: number | null;
    recorded_status_label: string | null;
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

function Row({
    item,
    enterprise,
    statuses,
    canUpdate,
}: {
    item: Item;
    enterprise: Props["enterprise"];
    statuses: Record<string, string>;
    canUpdate: boolean;
}) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState(item.status);
    const [dueDate, setDueDate] = useState(item.due_date ?? "");

    return (
        <article className="border rounded p-3 bg-white">
            <div className="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <strong>{item.version.title_ar}</strong>
                <StatusBadge
                    label={item.status_label}
                    tone={item.status === "completed" ? "success" : "warning"}
                />
            </div>
            <p className="mb-1 text-muted">
                {item.category_label} - نسخة {item.version.version_number}
            </p>
            <p className="mb-1">
                {item.version.guidance_pending
                    ? "الإرشاد بانتظار مدخلات بيكون"
                    : item.version.guidance_ar}
            </p>
            {item.needs_version_review ? (
                <div className="alert alert-warning py-2" role="status">
                    صدر إصدار جديد يحتاج مراجعة. حالة الإصدار{" "}
                    {item.recorded_version_number}: {item.recorded_status_label}
                    ؛ لم تُعتمد للإصدار الحالي.
                </div>
            ) : null}
            {item.missing_document ? (
                <p className="mb-1 text-danger">وثيقة مطلوبة لم تُستكمل</p>
            ) : null}
            {item.due_date ? (
                <p className="mb-1">
                    الموعد: {item.due_date}
                    {item.overdue
                        ? " (متأخر)"
                        : item.approaching
                          ? " (قريب)"
                          : ""}
                </p>
            ) : null}
            {item.action_needed ? (
                <p className="mb-2 fw-semibold">إجراء مطلوب</p>
            ) : null}
            {canUpdate ? (
                <div className="d-flex flex-wrap gap-2">
                    <select
                        aria-label="الحالة"
                        className="form-select w-auto"
                        style={{ minHeight: "44px" }}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                    >
                        {Object.entries(statuses).map(([k, l]) => (
                            <option key={k} value={k}>
                                {l}
                            </option>
                        ))}
                    </select>
                    <input
                        aria-label="الموعد"
                        type="date"
                        className="form-control w-auto"
                        style={{ minHeight: "44px" }}
                        value={dueDate}
                        onChange={(e) => setDueDate(e.target.value)}
                    />
                    <button
                        type="button"
                        className="btn btn-primary"
                        style={{ minHeight: "44px" }}
                        disabled={saving}
                        onClick={() =>
                            router.put(
                                `/enterprises/${enterprise.public_id}/compliance/${item.requirement_public_id}`,
                                {
                                    status,
                                    due_date: dueDate || null,
                                    version_number: item.version.version_number,
                                },
                                {
                                    preserveScroll: true,
                                    onStart: () => {
                                        setSaving(true);
                                        setError(null);
                                    },
                                    onError: (errors) =>
                                        setError(
                                            Object.values(errors).join(" "),
                                        ),
                                    onFinish: () => setSaving(false),
                                },
                            )
                        }
                    >
                        حفظ
                    </button>
                </div>
            ) : null}
            {error ? (
                <p className="text-danger mt-2 mb-0" role="alert">
                    {error}
                </p>
            ) : null}
        </article>
    );
}

export default function Enterprise({
    enterprise,
    items,
    statuses,
    canUpdate,
}: Props) {
    return (
        <AppLayout title="الامتثال">
            <div className="app-page">
                <PageHeader
                    title="الامتثال"
                    description={`${enterprise.business_name} - المتطلبات المطبقة وحالتها.`}
                />
                <div className="d-flex flex-column gap-3">
                    {items.length === 0 ? (
                        <EmptyState title="لا توجد متطلبات منشورة بعد" />
                    ) : null}
                    {items.map((item) => (
                        <Row
                            key={`${item.requirement_public_id}-${item.version.version_number}-${item.status}-${item.due_date}`}
                            item={item}
                            enterprise={enterprise}
                            statuses={statuses}
                            canUpdate={canUpdate}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
