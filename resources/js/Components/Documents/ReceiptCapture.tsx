import { router, useForm, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import type { DocumentRow } from "../../types/bookkeeping";
import type { BeaconSharedProps } from "../../types/shared";
import { handleInertiaConflict } from "../../lib/inertiaConflict";

/** Pick the document that appeared after an upload (list is newest-first). */
export function findNewDocument(before: ReadonlySet<string>, after: DocumentRow[] | undefined): DocumentRow | null {
    return (after ?? []).find((d) => !before.has(d.public_id)) ?? null;
}

type Step = "pick" | "ready" | "uploading" | "reading" | "error";

/**
 * Mobile-first receipt capture: camera or file → upload (existing documents.store) → read
 * (existing documents.extractions.store) → the server redirects to the review page.
 * Provider-agnostic: if reading is unavailable the review page opens in manual-entry mode.
 */
export function ReceiptCapture({ enterpriseId, currentIds, uploadUrl }: { enterpriseId: string; currentIds: string[]; uploadUrl?: string }) {
    const page = usePage();
    const form = useForm<{ file: File | null; title: string }>({ file: null, title: "" });
    const [step, setStep] = useState<Step>("pick");
    const [preview, setPreview] = useState<string | null>(null);
    const [info, setInfo] = useState<string | null>(null);
    const before = useRef<Set<string>>(new Set(currentIds));

    useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);

    const choose = (file: File | null) => {
        form.clearErrors();
        form.setData("file", file);
        if (preview) URL.revokeObjectURL(preview);
        setPreview(file && file.type.startsWith("image/") && typeof URL.createObjectURL === "function" ? URL.createObjectURL(file) : null);
        setStep(file ? "ready" : "pick");
    };

    const upload = () => {
        before.current = new Set(currentIds);
        setStep("uploading");
        form.post(uploadUrl ?? `/enterprises/${enterpriseId}/documents?extract=1`, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setStep("error"),
            // 409 + X-Inertia-Location (asset version changed): full load instead of an error.
            onHttpException: (r) => (handleInertiaConflict(r) ? false : undefined),
            onSuccess: (p) => {
                // With extract=1 the server goes straight to the review page (Extractions/Show).
                if ((p as { component?: string } | undefined)?.component === "Extractions/Show") return;
                const props = (p?.props ?? page.props) as Partial<BeaconSharedProps> & { documents?: { data?: DocumentRow[] } };
                const id = props.flash?.created_document_id ?? findNewDocument(before.current, props.documents?.data)?.public_id;
                if (props.flash?.info) setInfo(props.flash.info);
                if (!id) { setStep("error"); return; }
                setStep("reading");
                router.post(`/documents/${id}/extractions`, {}, {
                    onError: () => setStep("error"),
                    onHttpException: (r) => (handleInertiaConflict(r) ? false : undefined),
                });
            },
        });
    };

    const busy = step === "uploading" || step === "reading";
    const pct = form.progress?.percentage ?? 0;

    return (
        <div className="d-flex flex-column gap-3">
            <p className="mb-0 text-body-secondary">صوّر الإيصال أو الفاتورة، وسنقرأ المبلغ والتاريخ والمورد لتراجعها قبل الحفظ.</p>
            <div className="d-grid gap-2 d-sm-flex">
                <label className={`btn btn-primary${busy ? " disabled" : ""}`} style={{ minHeight: "56px", display: "inline-flex", alignItems: "center", justifyContent: "center" }}>
                    <i className="bi bi-camera me-2" aria-hidden="true" />
                    تصوير الإيصال
                    <input type="file" accept="image/*" capture="environment" className="visually-hidden" data-testid="camera-input"
                        disabled={busy} onChange={(e) => choose(e.target.files?.[0] ?? null)} />
                </label>
                <label className={`btn btn-outline-primary${busy ? " disabled" : ""}`} style={{ minHeight: "56px", display: "inline-flex", alignItems: "center", justifyContent: "center" }}>
                    <i className="bi bi-paperclip me-2" aria-hidden="true" />
                    اختيار ملف
                    <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" className="visually-hidden" data-testid="file-input"
                        disabled={busy} onChange={(e) => choose(e.target.files?.[0] ?? null)} />
                </label>
            </div>

            {form.data.file ? (
                <div className="app-mobile-record d-flex gap-3 align-items-center">
                    {preview ? (
                        <img src={preview} alt="معاينة الإيصال" style={{ width: 72, height: 72, objectFit: "cover", borderRadius: 10 }} />
                    ) : (
                        <i className="bi bi-file-earmark-pdf fs-2" aria-hidden="true" />
                    )}
                    <div className="flex-grow-1 text-truncate">
                        <div className="fw-semibold text-truncate">{form.data.file.name}</div>
                        <div className="small text-body-secondary"><bdi dir="ltr">{Math.max(1, Math.round(form.data.file.size / 1024))} KB</bdi></div>
                    </div>
                    {!busy ? (
                        <button type="button" className="btn btn-link" onClick={() => choose(null)} aria-label="إزالة الملف">
                            <i className="bi bi-x-lg" aria-hidden="true" />
                        </button>
                    ) : null}
                </div>
            ) : null}

            {step === "uploading" ? (
                <div className="progress" role="progressbar" aria-label="جارٍ رفع الإيصال" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}>
                    <div className="progress-bar" style={{ width: `${pct}%` }} />
                </div>
            ) : null}
            <div aria-live="polite" className="small">
                {step === "uploading" ? `جارٍ الرفع… ${pct}%` : step === "reading" ? "جارٍ قراءة الإيصال… سيفتح نموذج المراجعة تلقائياً." : null}
            </div>
            {info ? <div className="alert alert-info mb-0" role="status">{info}</div> : null}
            {step === "error" || form.errors.file ? (
                <div className="alert alert-danger mb-0" role="alert">
                    {form.errors.file ?? "تعذّر إكمال العملية. يمكنك المحاولة مرة أخرى، أو اختيار المستند من القائمة وتسجيله يدوياً."}
                </div>
            ) : null}

            <button type="button" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={!form.data.file || busy} onClick={upload}>
                <i className="bi bi-upload me-2" aria-hidden="true" />
                {busy ? "يرجى الانتظار…" : "رفع ومراجعة"}
            </button>
        </div>
    );
}
