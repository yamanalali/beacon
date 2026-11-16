import { router, useForm, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { useT } from "../../Hooks/useT";
import type { DocumentRow } from "../../types/bookkeeping";
import type { BeaconSharedProps } from "../../types/shared";
import { handleInertiaConflict } from "../../lib/inertiaConflict";

/** Pick the document that appeared after an upload (list is newest-first). */
export function findNewDocument(before: ReadonlySet<string>, after: DocumentRow[] | undefined): DocumentRow | null {
    return (after ?? []).find((d) => !before.has(d.public_id)) ?? null;
}

type Step = "pick" | "ready" | "uploading" | "reading" | "error";

function stopStream(stream: MediaStream | null) {
    stream?.getTracks().forEach((track) => track.stop());
}

/**
 * Mobile-first receipt capture: rear camera or file → upload → read → review.
 * Camera prefers getUserMedia({ facingMode: environment }); falls back to
 * <input capture="environment">. File picker never sets capture.
 */
export function ReceiptCapture({ enterpriseId, currentIds, uploadUrl }: { enterpriseId: string; currentIds: string[]; uploadUrl?: string }) {
    const t = useT();
    const page = usePage();
    const form = useForm<{ file: File | null; title: string }>({ file: null, title: "" });
    const [step, setStep] = useState<Step>("pick");
    const [preview, setPreview] = useState<string | null>(null);
    const [info, setInfo] = useState<string | null>(null);
    const [cameraOpen, setCameraOpen] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const before = useRef<Set<string>>(new Set(currentIds));
    const cameraInputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);

    useEffect(() => () => {
        if (preview) URL.revokeObjectURL(preview);
        stopStream(streamRef.current);
    }, [preview]);

    const choose = (file: File | null) => {
        form.clearErrors();
        form.setData("file", file);
        if (preview) URL.revokeObjectURL(preview);
        setPreview(file && file.type.startsWith("image/") && typeof URL.createObjectURL === "function" ? URL.createObjectURL(file) : null);
        setStep(file ? "ready" : "pick");
        setCameraError(null);
        if (cameraInputRef.current) cameraInputRef.current.value = "";
        if (fileInputRef.current) fileInputRef.current.value = "";
    };

    const closeCamera = () => {
        stopStream(streamRef.current);
        streamRef.current = null;
        setCameraOpen(false);
    };

    const openNativeCamera = () => {
        cameraInputRef.current?.click();
    };

    const openCamera = async () => {
        setCameraError(null);
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            openNativeCamera();
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
            });
            streamRef.current = stream;
            setCameraOpen(true);
            requestAnimationFrame(() => {
                const video = videoRef.current;
                if (!video) return;
                video.srcObject = stream;
                void video.play().catch(() => {});
            });
        } catch {
            openNativeCamera();
        }
    };

    const snapPhoto = () => {
        const video = videoRef.current;
        if (!video || video.videoWidth < 1 || video.videoHeight < 1) {
            setCameraError(t("documents.camera_not_ready", undefined, "الكاميرا ليست جاهزة بعد. حاول مرة أخرى."));
            return;
        }

        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            setCameraError(t("documents.camera_capture_failed", undefined, "تعذّر التقاط الصورة. استخدم اختيار ملف."));
            return;
        }
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) {
                setCameraError(t("documents.camera_capture_failed", undefined, "تعذّر التقاط الصورة. استخدم اختيار ملف."));
                return;
            }
            const stamp = new Date().toISOString().replace(/[:.]/g, "-");
            choose(new File([blob], `receipt-${stamp}.jpg`, { type: "image/jpeg" }));
            closeCamera();
        }, "image/jpeg", 0.92);
    };

    const upload = () => {
        before.current = new Set(currentIds);
        setStep("uploading");
        form.post(uploadUrl ?? `/enterprises/${enterpriseId}/documents?extract=1`, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setStep("error"),
            onHttpException: (r) => (handleInertiaConflict(r) ? false : undefined),
            onSuccess: (p) => {
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
            <p className="mb-0 text-body-secondary">{t("documents.receipt_capture_intro", undefined, "صوّر الإيصال أو الفاتورة، وسنقرأ المبلغ والتاريخ والمورد لتراجعها قبل الحفظ.")}</p>
            <div className="d-grid gap-2 d-sm-flex">
                <button
                    type="button"
                    className="btn btn-primary"
                    style={{ minHeight: "56px" }}
                    disabled={busy}
                    onClick={() => void openCamera()}
                >
                    <i className="bi bi-camera me-2" aria-hidden="true" />
                    {t("documents.capture_receipt", undefined, "تصوير الإيصال")}
                </button>
                <button
                    type="button"
                    className="btn btn-outline-primary"
                    style={{ minHeight: "56px" }}
                    disabled={busy}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <i className="bi bi-folder2-open me-2" aria-hidden="true" />
                    {t("documents.choose_file", undefined, "رفع ملف")}
                </button>
                {/* Rear-camera native fallback — never use for the file button. */}
                <input
                    ref={cameraInputRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="visually-hidden"
                    data-testid="camera-input"
                    tabIndex={-1}
                    disabled={busy}
                    onChange={(e) => choose(e.target.files?.[0] ?? null)}
                />
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    className="visually-hidden"
                    data-testid="file-input"
                    tabIndex={-1}
                    disabled={busy}
                    onChange={(e) => choose(e.target.files?.[0] ?? null)}
                />
            </div>

            {cameraError ? <div className="alert alert-warning mb-0" role="status">{cameraError}</div> : null}

            {cameraOpen ? (
                <div className="receipt-camera" role="dialog" aria-modal="true" aria-label={t("documents.camera_title", undefined, "تصوير الإيصال")}>
                    <div className="receipt-camera__panel">
                        <video ref={videoRef} className="receipt-camera__video" playsInline muted autoPlay />
                        <div className="receipt-camera__actions">
                            <button type="button" className="btn btn-light" style={{ minHeight: 48 }} onClick={closeCamera}>
                                {t("common.cancel", undefined, "إلغاء")}
                            </button>
                            <button type="button" className="btn btn-primary receipt-camera__shutter" style={{ minHeight: 56 }} onClick={snapPhoto}>
                                <i className="bi bi-camera-fill me-2" aria-hidden="true" />
                                {t("documents.take_photo", undefined, "التقاط")}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

            {form.data.file ? (
                <div className="app-mobile-record d-flex gap-3 align-items-center">
                    {preview ? (
                        <img src={preview} alt={t("documents.receipt_preview_alt", undefined, "معاينة الإيصال")} style={{ width: 72, height: 72, objectFit: "cover", borderRadius: 10 }} />
                    ) : (
                        <i className="bi bi-file-earmark-pdf fs-2" aria-hidden="true" />
                    )}
                    <div className="flex-grow-1 text-truncate">
                        <div className="fw-semibold text-truncate">{form.data.file.name}</div>
                        <div className="small text-body-secondary"><bdi dir="ltr">{Math.max(1, Math.round(form.data.file.size / 1024))} KB</bdi></div>
                    </div>
                    {!busy ? (
                        <button type="button" className="btn btn-link" onClick={() => choose(null)} aria-label={t("documents.remove_file", undefined, "إزالة الملف")}>
                            <i className="bi bi-x-lg" aria-hidden="true" />
                        </button>
                    ) : null}
                </div>
            ) : null}

            {step === "uploading" ? (
                <div className="progress" role="progressbar" aria-label={t("documents.uploading_receipt", undefined, "جارٍ رفع الإيصال")} aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}>
                    <div className="progress-bar" style={{ width: `${pct}%` }} />
                </div>
            ) : null}
            <div aria-live="polite" className="small">
                {step === "uploading" ? t("documents.uploading_pct", { pct }, `جارٍ الرفع… ${pct}%`) : step === "reading" ? t("documents.reading_receipt", undefined, "جارٍ قراءة الإيصال… سيفتح نموذج المراجعة تلقائياً.") : null}
            </div>
            {info ? <div className="alert alert-info mb-0" role="status">{info}</div> : null}
            {step === "error" || form.errors.file ? (
                <div className="alert alert-danger mb-0" role="alert">
                    {form.errors.file ?? t("documents.receipt_capture_error", undefined, "تعذّر إكمال العملية. يمكنك المحاولة مرة أخرى، أو اختيار المستند من القائمة وتسجيله يدوياً.")}
                </div>
            ) : null}

            <button type="button" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={!form.data.file || busy} onClick={upload}>
                <i className="bi bi-upload me-2" aria-hidden="true" />
                {busy ? t("documents.please_wait", undefined, "يرجى الانتظار…") : t("documents.upload_and_review", undefined, "رفع ومراجعة")}
            </button>
        </div>
    );
}
