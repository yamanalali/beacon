import { Link } from "@inertiajs/react";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

const JOURNEY = ["profile", "records", "compliance", "readiness", "referral"] as const;

const CAPABILITIES = [
    { key: "bookkeeping", icon: "bi-journal-text" },
    { key: "compliance", icon: "bi-shield-check" },
    { key: "readiness", icon: "bi-signpost-2" },
    { key: "referrals", icon: "bi-envelope-paper" },
] as const;

export default function Home() {
    const t = useT();

    return (
        <LandingLayout title={t("landing.meta_title")}>
            <section className="landing-hero" aria-labelledby="landing-hero-title">
                <div className="landing-hero-scene" aria-hidden="true">
                    <img
                        src="/images/brand/landing-hero-ledger.jpg"
                        alt=""
                        className="landing-hero-image"
                        width={1600}
                        height={900}
                        decoding="async"
                    />
                    <div className="landing-hero-veil" />
                </div>

                <div className="landing-hero-frame">
                    <div className="landing-hero-copy landing-rise">
                        <p className="landing-kicker">{t("landing.hero.kicker")}</p>
                        <h1 id="landing-hero-title" className="landing-hero-title">
                            {t("landing.hero.title")}
                        </h1>
                        <p className="landing-hero-lede">{t("landing.hero.subtitle")}</p>
                        <div className="landing-hero-actions">
                            <Link className="btn landing-btn landing-btn-accent landing-btn-lg" href="/login">
                                {t("landing.hero.cta_login")}
                            </Link>
                            <Link className="landing-text-link landing-text-link-on-dark" href="/about">
                                {t("landing.hero.cta_about")}
                                <i className="bi bi-arrow-left" aria-hidden="true" />
                            </Link>
                        </div>
                    </div>

                    <div className="landing-hero-stage landing-rise landing-rise-delay" aria-hidden="false">
                        <div className="landing-preview" role="img" aria-label={t("landing.moment.preview_label")}>
                            <div className="landing-preview-top">
                                <div>
                                    <p className="landing-preview-eyebrow">{t("landing.moment.preview_label")}</p>
                                    <p className="landing-preview-heading">{t("landing.moment.title")}</p>
                                </div>
                                <span className="landing-preview-chip">{t("landing.moment.chip")}</span>
                            </div>

                            <div className="landing-preview-stats">
                                <div className="landing-preview-stat is-lead">
                                    <span className="landing-preview-label">{t("landing.moment.income")}</span>
                                    <strong className="landing-preview-figure">{t("landing.moment.income_value")}</strong>
                                </div>
                                <div className="landing-preview-stat">
                                    <span className="landing-preview-label">{t("landing.moment.expense")}</span>
                                    <strong className="landing-preview-figure">{t("landing.moment.expense_value")}</strong>
                                </div>
                            </div>

                            <div className="landing-preview-progress" aria-hidden="true">
                                <div className="landing-preview-progress-head">
                                    <span>{t("landing.moment.readiness")}</span>
                                    <span className="landing-preview-progress-value">{t("landing.moment.readiness_value")}</span>
                                </div>
                                <div className="landing-preview-track">
                                    <span className="landing-preview-fill" style={{ width: "68%" }} />
                                    <span className="landing-preview-tick" style={{ insetInlineStart: "20%" }} />
                                    <span className="landing-preview-tick" style={{ insetInlineStart: "40%" }} />
                                    <span className="landing-preview-tick" style={{ insetInlineStart: "60%" }} />
                                    <span className="landing-preview-tick" style={{ insetInlineStart: "80%" }} />
                                </div>
                            </div>

                            <div className="landing-preview-alert">
                                <i className="bi bi-exclamation-circle" aria-hidden="true" />
                                <span>{t("landing.moment.alert")}</span>
                            </div>

                            <ul className="landing-preview-list">
                                <li>
                                    <span>{t("landing.moment.tx1")}</span>
                                    <span className="landing-preview-amount">{t("landing.moment.tx1_amount")}</span>
                                </li>
                                <li>
                                    <span>{t("landing.moment.tx2")}</span>
                                    <span className="landing-preview-amount">{t("landing.moment.tx2_amount")}</span>
                                </li>
                                <li>
                                    <span>{t("landing.moment.tx3")}</span>
                                    <span className="landing-preview-amount">{t("landing.moment.tx3_amount")}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section className="landing-section" aria-labelledby="landing-journey-title">
                <div className="landing-container">
                    <div className="landing-section-head">
                        <p className="landing-eyebrow">{t("landing.journey.eyebrow")}</p>
                        <h2 id="landing-journey-title" className="landing-section-title">
                            {t("landing.journey.title")}
                        </h2>
                        <p className="landing-prose landing-prose-wide">{t("landing.journey.body")}</p>
                    </div>
                    <ol className="landing-journey">
                        {JOURNEY.map((step, index) => (
                            <li key={step} className="landing-journey-step">
                                <span className="landing-journey-index">{String(index + 1).padStart(2, "0")}</span>
                                <h3 className="landing-journey-title">{t(`landing.journey.${step}`)}</h3>
                                <p className="landing-journey-desc">{t(`landing.journey.${step}_desc`)}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            <section className="landing-section landing-section-paper" aria-labelledby="landing-platform-title">
                <div className="landing-container">
                    <div className="landing-split">
                        <div className="landing-section-head">
                            <p className="landing-eyebrow">{t("landing.platform.eyebrow")}</p>
                            <h2 id="landing-platform-title" className="landing-section-title">
                                {t("landing.platform.title")}
                            </h2>
                            <p className="landing-prose">{t("landing.platform.intro")}</p>
                        </div>
                        <ul className="landing-capability-list">
                            {CAPABILITIES.map((item) => (
                                <li key={item.key} className="landing-capability">
                                    <span className="landing-capability-icon" aria-hidden="true">
                                        <i className={`bi ${item.icon}`} />
                                    </span>
                                    <div>
                                        <h3>{t(`landing.platform.${item.key}`)}</h3>
                                        <p>{t(`landing.platform.${item.key}_desc`)}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>

            <section className="landing-section" aria-labelledby="landing-trust-title">
                <div className="landing-container landing-trust">
                    <div className="landing-section-head">
                        <p className="landing-eyebrow">{t("landing.trust.eyebrow")}</p>
                        <h2 id="landing-trust-title" className="landing-section-title">
                            {t("landing.trust.title")}
                        </h2>
                        <p className="landing-prose">{t("landing.trust.intro")}</p>
                    </div>
                    <ul className="landing-trust-list">
                        <li>
                            <span className="landing-trust-mark" aria-hidden="true" />
                            {t("landing.trust.docs")}
                        </li>
                        <li>
                            <span className="landing-trust-mark" aria-hidden="true" />
                            {t("landing.trust.roles")}
                        </li>
                        <li>
                            <span className="landing-trust-mark" aria-hidden="true" />
                            {t("landing.trust.audit")}
                        </li>
                    </ul>
                </div>
            </section>

            <section className="landing-cta-band" aria-labelledby="landing-cta-title">
                <div className="landing-container landing-cta-inner">
                    <div>
                        <h2 id="landing-cta-title" className="landing-cta-title">
                            {t("landing.cta.title")}
                        </h2>
                        <p className="landing-cta-body">{t("landing.cta.body")}</p>
                    </div>
                    <div className="landing-hero-actions">
                        <Link className="btn landing-btn landing-btn-light landing-btn-lg" href="/contact">
                            {t("landing.cta.contact")}
                        </Link>
                        <Link className="landing-text-link landing-text-link-on-dark" href="/case-studies">
                            {t("landing.cta.cases")}
                            <i className="bi bi-arrow-left" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
