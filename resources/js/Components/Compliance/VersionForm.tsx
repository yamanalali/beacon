import { useForm } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import { TextInput } from "../Forms/TextInput";

export type VersionFormValues = {
    title_ar: string;
    description_ar: string;
    guidance_ar: string;
    deadline_note_ar: string;
    effective_from: string;
    requires_document: boolean;
    change_summary: string;
};

export const emptyVersionValues = (): VersionFormValues => ({
    title_ar: "",
    description_ar: "",
    guidance_ar: "",
    deadline_note_ar: "",
    effective_from: "",
    requires_document: false,
    change_summary: "",
});

type Props = {
    idPrefix: string;
    /** POST creates a new draft version; PUT updates an existing draft in place. */
    method: "post" | "put";
    url: string;
    initial: VersionFormValues;
    submitLabel: string;
    /** A new version must say what changed; editing a draft does not. */
    requireSummary?: boolean;
    onDone: () => void;
};

/** Shared form for "new version" (versions.store) and "edit draft" (versions.update) of a compliance requirement. */
export function VersionForm({ idPrefix, method, url, initial, submitLabel, requireSummary = false, onDone }: Props) {
    const t = useT();
    const form = useForm<VersionFormValues>(initial);
    const set = (key: keyof VersionFormValues, value: string | boolean) => form.setData((data) => ({ ...data, [key]: value }));

    return (
        <form
            className="border rounded p-3 mt-3 bg-light"
            onSubmit={(e) => {
                e.preventDefault();
                form[method](url, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <TextInput id={`${idPrefix}-title`} name="title_ar" label={t("documents.show.title_label", undefined, "العنوان")} value={form.data.title_ar} onChange={(v) => set("title_ar", v)} error={form.errors.title_ar} required />
            <div className="mb-3">
                <label htmlFor={`${idPrefix}-description`} className="form-label">{t("compliance.admin.description_field", undefined, "الوصف")}</label>
                <textarea id={`${idPrefix}-description`} className={`form-control${form.errors.description_ar ? " is-invalid" : ""}`} rows={2} value={form.data.description_ar} onChange={(e) => set("description_ar", e.target.value)} />
                {form.errors.description_ar ? <div className="invalid-feedback d-block" role="alert">{form.errors.description_ar}</div> : null}
            </div>
            <div className="mb-3">
                <label htmlFor={`${idPrefix}-guidance`} className="form-label">{t("compliance.admin.guidance", undefined, "الإرشاد المعتمد")}</label>
                <textarea id={`${idPrefix}-guidance`} className={`form-control${form.errors.guidance_ar ? " is-invalid" : ""}`} rows={3} value={form.data.guidance_ar} onChange={(e) => set("guidance_ar", e.target.value)} aria-describedby={`${idPrefix}-guidance-help`} />
                <div id={`${idPrefix}-guidance-help`} className="form-text">{t("compliance.admin.guidance_help", undefined, "اتركه فارغاً إذا كان المحتوى بانتظار اعتماد بيكون.")}</div>
                {form.errors.guidance_ar ? <div className="invalid-feedback d-block" role="alert">{form.errors.guidance_ar}</div> : null}
            </div>
            <TextInput id={`${idPrefix}-deadline`} name="deadline_note_ar" label={t("compliance.admin.deadline_note", undefined, "ملاحظة الموعد النهائي")} value={form.data.deadline_note_ar} onChange={(v) => set("deadline_note_ar", v)} error={form.errors.deadline_note_ar} />
            <TextInput id={`${idPrefix}-effective`} name="effective_from" type="date" label={t("compliance.admin.effective_from", undefined, "تاريخ السريان")} value={form.data.effective_from} onChange={(v) => set("effective_from", v)} error={form.errors.effective_from} />
            <div className="form-check mb-3">
                <input id={`${idPrefix}-requires-document`} type="checkbox" className="form-check-input" checked={form.data.requires_document} onChange={(e) => set("requires_document", e.target.checked)} />
                <label htmlFor={`${idPrefix}-requires-document`} className="form-check-label">{t("compliance.admin.requires_document", undefined, "يتطلب وثيقة")}</label>
            </div>
            <TextInput id={`${idPrefix}-summary`} name="change_summary" label={t("compliance.admin.change_summary_field", undefined, "ملخص التغيير")} value={form.data.change_summary} onChange={(v) => set("change_summary", v)} error={form.errors.change_summary} required={requireSummary} help={requireSummary ? t("compliance.admin.change_summary_help", undefined, "يظهر في سجل النسخ ويوضح ما الذي تغيّر.") : undefined} />
            <div className="d-flex flex-wrap gap-2">
                <button type="submit" className="btn btn-primary" style={{ minHeight: "44px" }} disabled={form.processing || !form.data.title_ar.trim() || (requireSummary && !form.data.change_summary.trim())}>
                    {form.processing ? <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" /> : null}
                    {submitLabel}
                </button>
                <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "44px" }} onClick={onDone}>{t("common.cancel", undefined, "إلغاء")}</button>
            </div>
        </form>
    );
}
