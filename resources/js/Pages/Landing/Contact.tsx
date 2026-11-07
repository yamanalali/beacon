import { Link } from "@inertiajs/react";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

export default function Contact() {
    const t = useT();

    return (
        <LandingLayout title={`${t("landing.nav.contact")} — ${t("common.app_name")}`}>
            <section className="landing-page-hero">
                <div className="landing-container">
                    <p className="landing-eyebrow">{t("landing.contact.eyebrow")}</p>
                    <h1 className="landing-page-title">{t("landing.contact.title")}</h1>
                    <p className="landing-page-lede">{t("landing.contact.lede")}</p>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-contact">
                    <div className="landing-contact-card">
                        <h2 className="landing-contact-label">{t("landing.footer.email")}</h2>
                        <a className="landing-contact-value" href="mailto:info@beacon-cd.com">
                            info@beacon-cd.com
                        </a>
                        <p className="landing-contact-hint">{t("landing.contact.email_hint")}</p>
                    </div>
                    <div className="landing-contact-card">
                        <h2 className="landing-contact-label">{t("landing.footer.website")}</h2>
                        <a
                            className="landing-contact-value"
                            href="https://beacon-cd.com/"
                            target="_blank"
                            rel="noreferrer"
                        >
                            beacon-cd.com
                        </a>
                        <p className="landing-contact-hint">{t("landing.contact.web_hint")}</p>
                    </div>
                    <div className="landing-contact-card landing-contact-card-wide">
                        <h2 className="landing-contact-label">{t("landing.contact.access_title")}</h2>
                        <p className="landing-contact-hint mb-3">{t("landing.contact.access_body")}</p>
                        <Link className="btn landing-btn landing-btn-accent" href="/login">
                            {t("landing.hero.cta_login")}
                        </Link>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
