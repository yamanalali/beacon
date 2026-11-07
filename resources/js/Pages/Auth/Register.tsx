import { Link, useForm, usePage } from "@inertiajs/react";
import type { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function Register() {
    const t = useT();
    const flash = (usePage().props as { flash?: { success?: string } }).flash;
    const form = useForm({
        name: "",
        email: "",
        phone: "",
        business_name: "",
        city: "",
        notes: "",
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post("/register");
    }

    if (flash?.success) {
        return (
            <GuestAuthLayout
                title={t("register.success_title")}
                eyebrow={t("register.eyebrow")}
                intro={t("register.success_intro")}
                cardClassName="app-auth-card--wide"
            >
                <div className="register-success" role="status">
                    <div className="register-success__icon" aria-hidden="true">
                        <i className="bi bi-check2-circle" />
                    </div>
                    <p className="register-success__message mb-0">{flash.success}</p>
                    <ul className="register-success__next list-unstyled mb-0">
                        <li>
                            <i className="bi bi-envelope-check" aria-hidden="true" />
                            <span>{t("register.success_next_review")}</span>
                        </li>
                        <li>
                            <i className="bi bi-person-lock" aria-hidden="true" />
                            <span>{t("register.success_next_account")}</span>
                        </li>
                    </ul>
                    <div className="register-actions">
                        <Link href="/" className="btn btn-primary w-100" style={{ minHeight: 48 }}>
                            {t("auth.guest.back_home")}
                        </Link>
                        <Link href="/login" className="btn btn-outline-secondary w-100" style={{ minHeight: 48 }}>
                            {t("auth.login.title")}
                        </Link>
                    </div>
                </div>
            </GuestAuthLayout>
        );
    }

    return (
        <GuestAuthLayout
            title={t("register.title")}
            eyebrow={t("register.eyebrow")}
            intro={t("register.subtitle")}
            cardClassName="app-auth-card--wide register-card"
        >
            <div className="register">
                <ul className="register-trust list-unstyled mb-0">
                    <li>
                        <i className="bi bi-clipboard-check" aria-hidden="true" />
                        <span>{t("register.trust.review")}</span>
                    </li>
                    <li>
                        <i className="bi bi-shield-lock" aria-hidden="true" />
                        <span>{t("register.trust.no_auto_account")}</span>
                    </li>
                    <li>
                        <i className="bi bi-chat-dots" aria-hidden="true" />
                        <span>{t("register.trust.follow_up")}</span>
                    </li>
                </ul>

                <form className="register-form" onSubmit={submit} noValidate>
                    <section className="register-section" aria-labelledby="register-contact-heading">
                        <div className="register-section__head">
                            <span className="register-section__index" aria-hidden="true">
                                1
                            </span>
                            <div>
                                <h2 id="register-contact-heading" className="register-section__title">
                                    {t("register.section.contact")}
                                </h2>
                                <p className="register-section__hint mb-0">{t("register.section.contact_hint")}</p>
                            </div>
                        </div>
                        <div className="register-grid">
                            <TextInput
                                id="name"
                                name="name"
                                autoComplete="name"
                                label={t("register.name")}
                                value={form.data.name}
                                onChange={(v) => form.setData("name", v)}
                                error={form.errors.name}
                                required
                            />
                            <TextInput
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                label={t("register.email")}
                                value={form.data.email}
                                onChange={(v) => form.setData("email", v)}
                                error={form.errors.email}
                                required
                            />
                            <TextInput
                                id="phone"
                                name="phone"
                                type="tel"
                                autoComplete="tel"
                                label={t("register.phone")}
                                help={t("register.phone_help")}
                                value={form.data.phone}
                                onChange={(v) => form.setData("phone", v)}
                                error={form.errors.phone}
                            />
                        </div>
                    </section>

                    <section className="register-section" aria-labelledby="register-business-heading">
                        <div className="register-section__head">
                            <span className="register-section__index" aria-hidden="true">
                                2
                            </span>
                            <div>
                                <h2 id="register-business-heading" className="register-section__title">
                                    {t("register.section.business")}
                                </h2>
                                <p className="register-section__hint mb-0">{t("register.section.business_hint")}</p>
                            </div>
                        </div>
                        <div className="register-grid">
                            <TextInput
                                id="business_name"
                                name="business_name"
                                autoComplete="organization"
                                label={t("register.business_name")}
                                value={form.data.business_name}
                                onChange={(v) => form.setData("business_name", v)}
                                error={form.errors.business_name}
                                required
                            />
                            <TextInput
                                id="city"
                                name="city"
                                autoComplete="address-level2"
                                label={t("register.city")}
                                value={form.data.city}
                                onChange={(v) => form.setData("city", v)}
                                error={form.errors.city}
                            />
                            <div className="mb-3 w-100 register-notes">
                                <label htmlFor="notes" className="form-label">
                                    {t("register.notes")}
                                </label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    className={`form-control w-100${form.errors.notes ? " is-invalid" : ""}`}
                                    value={form.data.notes}
                                    aria-invalid={form.errors.notes ? true : undefined}
                                    onChange={(e) => form.setData("notes", e.target.value)}
                                />
                                <p className="form-text mb-0">{t("register.notes_help")}</p>
                                {form.errors.notes ? (
                                    <div className="invalid-feedback d-block">{form.errors.notes}</div>
                                ) : null}
                            </div>
                        </div>
                    </section>

                    <div className="register-actions">
                        <button
                            type="submit"
                            className="btn btn-primary w-100"
                            style={{ minHeight: 48 }}
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
                                    {t("register.submitting")}
                                </>
                            ) : (
                                <>
                                    <i className="bi bi-send me-2" aria-hidden="true" />
                                    {t("register.submit")}
                                </>
                            )}
                        </button>
                        <p className="register-note mb-0">{t("register.privacy_note")}</p>
                    </div>
                </form>

                <p className="register-login mb-0">
                    {t("register.have_account")}{" "}
                    <Link href="/login">{t("auth.login.title")}</Link>
                </p>
            </div>
        </GuestAuthLayout>
    );
}
