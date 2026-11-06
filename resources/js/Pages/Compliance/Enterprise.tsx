import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";

type Submission = {
    id: number;
    original_name: string | null;
    title: string | null;
    submitted_at: string | null;
    review_status: string;
    review_status_label: string;
    review_note: string | null;
    document_public_id: string | null;
    document_url: string | null;
};

type Item = {
    requirement_public_id: string;
    category_label: string;
    version: { version_number: number; title_ar: string; guidance_ar: string | null; guidance_pending: boolean; deadline_note_ar: string | null };
    requires_document: boolean;
    needs_version_review?: boolean;
    recorded_version_number?: number | null;
    recorded_status_label?: string | null;
    status: string;
    status_label: string;
    due_date: string | null;
    notes: string | null;
    missing_document: boolean;
    overdue: boolean;
    approaching: boolean;
    action_needed: boolean;
    can_upload: boolean;
    can_review: boolean;
    latest_submission: Submission | null;
};

type Props = {
    enterprise: { public_id: string; business_name: string };
    items: Item[];
    statuses: Record<string, string>;
    canUpdate: boolean;
    canSubmit?: boolean;
    canReview?: boolean;
};

function tone(item: Item): StatusTone {
    if (item.status === "completed") return "success";
    if (item.status === "awaiting_review") return "warning";
    if (item.status === "rejected" || item.overdue) return "danger";
    if (item.status === "needs_resubmission") return "warning";
    return "warning";
}

function UploadForm({ enterpriseId, requirementId }: { enterpriseId: string; requirementId: string }) {
    const form = useForm<{ file: File | null; title: string }>({ file: null, title: "" });

    return (
        <form
            className="border rounded-3 p-3 mt-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/enterprises/${enterpriseId}/compliance/${requirementId}/submissions`, {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => form.reset("file", "title"),
                });
            }}
        >
            <p className="small text-body-secondary mb-2">ارفع الوثيقة المطلوبة للمراجعة. لن تُعتمد إلا بعد موافقة المستشار.</p>
            <label className="form-label fw-semibold" htmlFor={`file-${requirementId}`}>الملف (صورة أو PDF)</label>
            <input
                id={`file-${requirementId}`}
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                className={`form-control mb-2${form.errors.file ? " is-invalid" : ""}`}
                style={{ minHeight: "48px", fontSize: "16px" }}
                onChange={(event) => form.setData("file", event.target.files?.[0] ?? null)}
            />
            {form.errors.file ? <div className="invalid-feedback d-block" role="alert">{form.errors.file}</div> : null}
            <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing || form.data.file === null}>
                <i className="bi bi-upload me-2" aria-hidden="true" />
                {form.processing ? "جارٍ الرفع..." : "رفع للمراجعة"}
            </button>
        </form>
    );
}

function ReviewActions({
    enterpriseId,
    requirementId,
    submissionId,
}: {
    enterpriseId: string;
    requirementId: string;
    submissionId: number;
}) {
    const [note, setNote] = useState("");
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const review = (decision: "approve" | "reject" | "request_resubmission") => {
        setSaving(true);
        setError(null);
        router.post(
            `/enterprises/${enterpriseId}/compliance/${requirementId}/submissions/${submissionId}/review`,
            { decision, review_note: note || null },
            {
                preserveScroll: true,
                onError: (errors) => setError(Object.values(errors).join(" ")),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="border rounded-3 p-3 mt-3 bg-body-tertiary">
            <p className="fw-semibold mb-2">مراجعة الوثيقة</p>
            <label className="form-label" htmlFor={`note-${submissionId}`}>ملاحظة للمستفيد (اختياري)</label>
            <textarea
                id={`note-${submissionId}`}
                className="form-control mb-3"
                rows={2}
                value={note}
                onChange={(e) => setNote(e.target.value)}
                style={{ fontSize: "16px" }}
            />
            <div className="d-grid gap-2">
                <button type="button" className="btn btn-success" style={{ minHeight: "48px" }} disabled={saving} onClick={() => review("approve")}>
                    اعتماد
                </button>
                <button type="button" className="btn btn-outline-danger" style={{ minHeight: "48px" }} disabled={saving} onClick={() => review("reject")}>
                    رفض
                </button>
                <button type="button" className="btn btn-outline-warning" style={{ minHeight: "48px" }} disabled={saving} onClick={() => review("request_resubmission")}>
                    طلب وثيقة أخرى
                </button>
            </div>
            {error ? <p className="text-danger mt-2 mb-0" role="alert">{error}</p> : null}
        </div>
    );
}

function StaffStatusForm({
    item,
    enterprise,
    statuses,
}: {
    item: Item;
    enterprise: Props["enterprise"];
    statuses: Record<string, string>;
}) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState(item.status);
    const [dueDate, setDueDate] = useState(item.due_date ?? "");
    const id = item.requirement_public_id;
    const documentLocked = item.requires_document;

    return (
        <div className="row g-2 align-items-end pt-3">
            {!documentLocked ? (
                <div className="col-12 col-md-5">
                    <label className="form-label" htmlFor={`status-${id}`}>الحالة</label>
                    <select id={`status-${id}`} className="form-select" value={status} onChange={(e) => setStatus(e.target.value)}>
                        {Object.entries(statuses).map(([k, l]) => (
                            <option key={k} value={k}>{l}</option>
                        ))}
                    </select>
                </div>
            ) : null}
            <div className={documentLocked ? "col-12 col-md-8" : "col-12 col-md-4"}>
                <label className="form-label" htmlFor={`due-${id}`}>الموعد</label>
                <input id={`due-${id}`} type="date" className="form-control" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
            </div>
            <div className="col-12 col-md-3 d-grid">
                <button
                    type="button"
                    className="btn btn-primary"
                    disabled={saving}
                    onClick={() =>
                        router.put(
                            `/enterprises/${enterprise.public_id}/compliance/${id}`,
                            {
                                ...(documentLocked
                                    ? { status: item.status }
                                    : { status }),
                                due_date: dueDate || null,
                                version_number: item.version.version_number,
                            },
                            {
                                preserveScroll: true,
                                onStart: () => {
                                    setSaving(true);
                                    setError(null);
                                },
                                onError: (errors) => setError(Object.values(errors).join(" ")),
                                onFinish: () => setSaving(false),
                            },
                        )
                    }
                >
                    {documentLocked ? "حفظ الموعد" : "حفظ"}
                </button>
            </div>
            {documentLocked ? (
                <div className="col-12">
                    <p className="small text-body-secondary mb-0">الاكتمال يتم عبر اعتماد الوثيقة بعد رفعها من المنشأة.</p>
                </div>
            ) : null}
            {error ? <p className="text-danger mt-2 mb-0" role="alert">{error}</p> : null}
        </div>
    );
}

function Row({ item, enterprise, statuses, canUpdate }: { item: Item; enterprise: Props["enterprise"]; statuses: Record<string, string>; canUpdate: boolean }) {
    const submission = item.latest_submission;

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
            {item.needs_version_review ? <div className="alert alert-warning py-2" role="status">صدر إصدار جديد يحتاج مراجعة. حالة الإصدار {item.recorded_version_number}: {item.recorded_status_label}؛ لم تُعتمد للإصدار الحالي.</div> : null}
            {item.missing_document ? <p className="app-record-line"><span>الوثائق</span><span className="text-danger">وثيقة مطلوبة لم تُستكمل</span></p> : null}

            {submission ? (
                <div className="rounded-3 border p-3 mt-2">
                    <p className="mb-1 fw-semibold">{submission.title || submission.original_name || "وثيقة مرفوعة"}</p>
                    <p className="small text-body-secondary mb-2">
                        {submission.review_status_label}
                        {submission.submitted_at ? <> · <bdi dir="ltr">{new Date(submission.submitted_at).toLocaleDateString("ar")}</bdi></> : null}
                    </p>
                    {submission.review_note ? <p className="small mb-2">ملاحظة المراجعة: {submission.review_note}</p> : null}
                    {submission.document_url ? (
                        <a className="btn btn-outline-primary btn-sm" style={{ minHeight: "44px" }} href={submission.document_url}>
                            تنزيل الوثيقة
                        </a>
                    ) : null}
                </div>
            ) : null}

            {item.can_upload ? <UploadForm enterpriseId={enterprise.public_id} requirementId={item.requirement_public_id} /> : null}
            {item.can_review && submission ? (
                <ReviewActions enterpriseId={enterprise.public_id} requirementId={item.requirement_public_id} submissionId={submission.id} />
            ) : null}
            {canUpdate ? <StaffStatusForm item={item} enterprise={enterprise} statuses={statuses} /> : null}
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
                                <Row
                                    key={`${item.requirement_public_id}-${item.version.version_number}-${item.status}-${item.due_date}-${item.latest_submission?.id ?? "none"}`}
                                    item={{
                                        requires_document: false,
                                        can_upload: false,
                                        can_review: false,
                                        latest_submission: null,
                                        ...item,
                                    }}
                                    enterprise={enterprise}
                                    statuses={statuses}
                                    canUpdate={canUpdate}
                                />
                            ))}
                        </div>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}
