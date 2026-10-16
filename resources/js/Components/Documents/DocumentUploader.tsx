import { useForm } from "@inertiajs/react";
import { TextInput } from "../Forms/TextInput";

export function DocumentUploader({ enterpriseId, transactionId }: { enterpriseId: string; transactionId?: string }) {
    const form = useForm<{ file: File | null; title: string; transaction: string }>({
        file: null,
        title: "",
        transaction: transactionId ?? "",
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/enterprises/${enterpriseId}/documents`, {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => form.reset("file", "title"),
                });
            }}
        >
            <div className="app-upload-zone mb-3">
                <span className="app-upload-zone__icon"><i className="bi bi-cloud-arrow-up" aria-hidden="true" /></span>
                <p className="fw-semibold mb-1">أضف وثيقة إلى ملف منشأتك</p>
                <label htmlFor="document_file" className="form-label mb-1 fw-semibold">
                    الملف (صورة أو PDF، حتى 10 ميغابايت)
                </label>
                <input
                    id="document_file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    className={`form-control app-file-input${form.errors.file ? " is-invalid" : ""}`}
                    style={{ minHeight: "48px", fontSize: "16px" }}
                    aria-invalid={form.errors.file ? true : undefined}
                    aria-describedby={form.errors.file ? "document-file-error" : undefined}
                    onChange={(event) => form.setData("file", event.target.files?.[0] ?? null)}
                />
                {form.errors.file ? (
                    <div id="document-file-error" className="invalid-feedback d-block" role="alert">
                        {form.errors.file}
                    </div>
                ) : null}
            </div>
            <TextInput
                id="document_title"
                name="title"
                label="عنوان المستند (اختياري)"
                value={form.data.title}
                onChange={(value) => form.setData("title", value)}
                error={form.errors.title}
            />
            {form.progress ? (
                <div className="progress mb-3" role="progressbar" aria-label="جارٍ الرفع" aria-valuenow={form.progress.percentage ?? 0}>
                    <div className="progress-bar" style={{ width: `${form.progress.percentage ?? 0}%` }} />
                </div>
            ) : null}
            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing || form.data.file === null}>
                <i className="bi bi-upload me-2" aria-hidden="true" />
                {form.processing ? "جارٍ الرفع..." : "رفع المستند"}
            </button>
        </form>
    );
}
