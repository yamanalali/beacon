import { Link } from "@inertiajs/react";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

const FOCUS = ["systems", "compliance", "impact"] as const;

export default function About() {
    const t = useT();

    return (
        <LandingLayout title={`${t("landing.nav.about")} — ${t("common.app_name")}`}>
            <section className="landing-page-hero">
                <div className="landing-container">
                    <p className="landing-eyebrow">{t("landing.about.eyebrow")}</p>
                    <h1 className="landing-page-title">{t("landing.about.title")}</h1>
                    <p className="landing-page-lede">{t("landing.about.lede")}</p>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-prose-block">
                    <p>{t("landing.about.body")}</p>
                    <p>{t("landing.about.platform")}</p>
                </div>
            </section>

            <section className="landing-section landing-section-paper">
                <div className="landing-container">
                    <h2 className="landing-section-title">{t("landing.about.focus_title")}</h2>
                    <ul className="landing-focus-grid">
                        {FOCUS.map((key) => (
                            <li key={key} className="landing-focus-item">
                                <h3>{t(`landing.about.focus.${key}`)}</h3>
                                <p>{t(`landing.about.focus.${key}_desc`)}</p>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-inline-cta">
                    <p>{t("landing.about.cta_body")}</p>
                    <div className="landing-hero-actions">
                        <Link className="btn landing-btn landing-btn-accent" href="/contact">
                            {t("landing.hero.cta_contact")}
                        </Link>
                        <a
                            className="landing-text-link"
                            href="https://beacon-cd.com/"
                            target="_blank"
                            rel="noreferrer"
                        >
                            {t("landing.footer.website")}
                            <i className="bi bi-box-arrow-up-left" aria-hidden="true" />
                        </a>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
