import { useForm } from "@inertiajs/react";
import { useEffect, useState, type FormEvent } from "react";
import { SignaturePad } from "../../Components/Forms/SignaturePad";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

type Props = {
    can_skip?: boolean;
};

type Mode = "draw" | "upload";

export default function Onboarding({ can_skip = true }: Props) {
    const t = useT();
    const [mode, setMode] = useState<Mode>("draw");
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const form = useForm<{
        signature: File | null;
        signature_data: string | null;
    }>({
        signature: null,
        signature_data: null,
    });
    const skip = useForm({});

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function switchMode(next: Mode) {
        if (next === mode) {
            return;
        }
        setMode(next);
        if (next === "draw") {
            form.setData("signature", null);
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                setPreviewUrl(null);
            }
        } else {
            form.setData("signature_data", null);
        }
    }

    function onFileChange(file: File | null) {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        form.setData("signature", file);
        setPreviewUrl(file ? URL.createObjectURL(file) : null);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post("/onboarding/signature", {
            forceFormData: true,
        });
    }

    const canSave =
        mode === "draw" ? Boolean(form.data.signature_data) : Boolean(form.data.signature);

    return (
        <GuestAuthLayout
            title={t("onboarding.title")}
            eyebrow={t("onboarding.eyebrow")}
            intro={t("onboarding.subtitle")}
            cardClassName="app-auth-card--wide onboarding-card"
        >
            <div className="onboarding">
                <ol className="onboarding-steps" aria-label={t("onboarding.progress_label")}>
                    <li className="is-active">
                        <span className="onboarding-steps__index" aria-hidden="true">
                            1
                        </span>
                        <span>{t("onboarding.step_signature")}</span>
                    </li>
                    <li>
                        <span className="onboarding-steps__index" aria-hidden="true">
                            2
                        </span>
                        <span>{t("onboarding.step_workspace")}</span>
                    </li>
                </ol>

                <ul className="onboarding-benefits list-unstyled mb-0">
                    <li>
                        <i className="bi bi-file-earmark-text" aria-hidden="true" />
                        <span>{t("onboarding.benefit.invoices")}</span>
                    </li>
                    <li>
                        <i className="bi bi-shield-check" aria-hidden="true" />
                        <span>{t("onboarding.benefit.private")}</span>
                    </li>
                    <li>
                        <i className="bi bi-phone" aria-hidden="true" />
                        <span>{t("onboarding.benefit.mobile")}</span>
                    </li>
                </ul>

                <div className="onboarding-mode" role="tablist" aria-label={t("onboarding.mode_label")}>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "draw"}
                        className={`onboarding-mode__btn${mode === "draw" ? " is-active" : ""}`}
                        onClick={() => switchMode("draw")}
                    >
                        <i className="bi bi-pen" aria-hidden="true" />
                        <span className="onboarding-mode__title">{t("onboarding.draw")}</span>
                        <span className="onboarding-mode__hint">{t("onboarding.draw_tab_hint")}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "upload"}
                        className={`onboarding-mode__btn${mode === "upload" ? " is-active" : ""}`}
                        onClick={() => switchMode("upload")}
                    >
                        <i className="bi bi-image" aria-hidden="true" />
                        <span className="onboarding-mode__title">{t("onboarding.upload_optional")}</span>
                        <span className="onboarding-mode__hint">{t("onboarding.upload_tab_hint")}</span>
                    </button>
                </div>

                <form className="onboarding-form" onSubmit={submit}>
                    {mode === "draw" ? (
                        <div className="onboarding-panel" role="tabpanel">
                            <label className="form-label fw-semibold mb-2">{t("onboarding.draw_label")}</label>
                            <SignaturePad onChange={(dataUrl) => form.setData("signature_data", dataUrl)} />
                            {form.errors.signature_data ? (
                                <div className="invalid-feedback d-block">{form.errors.signature_data}</div>
                            ) : null}
                            {form.errors.signature && !form.errors.signature_data ? (
                                <div className="invalid-feedback d-block">{form.errors.signature}</div>
                            ) : null}
                        </div>
                    ) : (
                        <div className="onboarding-panel" role="tabpanel">
                            <label htmlFor="signature-file" className="form-label fw-semibold mb-2">
                                {t("onboarding.upload_label")}
                            </label>
                            <label
                                htmlFor="signature-file"
                                className={`onboarding-upload${previewUrl ? " has-preview" : ""}`}
                            >
                                {previewUrl ? (
                                    <img src={previewUrl} alt="" className="onboarding-upload__preview" />
                                ) : (
                                    <span className="onboarding-upload__empty">
                                        <i className="bi bi-cloud-arrow-up" aria-hidden="true" />
                                        <strong>{t("onboarding.upload_cta")}</strong>
                                        <span>{t("onboarding.upload_hint")}</span>
                                    </span>
                                )}
                                <input
                                    id="signature-file"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="visually-hidden"
                                    onChange={(e) => onFileChange(e.target.files?.[0] ?? null)}
                                />
                            </label>
                            {previewUrl ? (
                                <button
                                    type="button"
                                    className="btn btn-link px-0 mt-2"
                                    onClick={() => onFileChange(null)}
                                >
                                    {t("onboarding.upload_remove")}
                                </button>
                            ) : null}
                            {form.errors.signature ? (
                                <div className="invalid-feedback d-block">{form.errors.signature}</div>
                            ) : null}
                        </div>
                    )}

                    <div className="onboarding-actions">
                        <button
                            type="submit"
                            className="btn btn-primary w-100"
                            style={{ minHeight: 48 }}
                            disabled={form.processing || !canSave}
                        >
                            {form.processing ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
                                    {t("onboarding.saving")}
                                </>
                            ) : (
                                <>
                                    <i className="bi bi-check2-circle me-2" aria-hidden="true" />
                                    {t("onboarding.save")}
                                </>
                            )}
                        </button>

                        {can_skip ? (
                            <button
                                type="button"
                                className="btn btn-outline-secondary w-100"
                                style={{ minHeight: 48 }}
                                disabled={skip.processing || form.processing}
                                onClick={() => skip.post("/onboarding/skip")}
                            >
                                {t("onboarding.skip")}
                            </button>
                        ) : null}

                        <p className="onboarding-note mb-0">{t("onboarding.privacy_note")}</p>
                    </div>
                </form>
            </div>
        </GuestAuthLayout>
    );
}
