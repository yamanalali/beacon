import { Link } from "@inertiajs/react";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

type Props = {
    cases: string[];
};

export default function CaseStudies({ cases }: Props) {
    const t = useT();

    return (
        <LandingLayout title={`${t("landing.nav.case_studies")} — ${t("common.app_name")}`}>
            <section className="landing-page-hero">
                <div className="landing-container">
                    <p className="landing-eyebrow">{t("landing.cases.eyebrow")}</p>
                    <h1 className="landing-page-title">{t("landing.cases.title")}</h1>
                    <p className="landing-page-lede">{t("landing.cases.lede")}</p>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-cases">
                    {cases.map((key, index) => (
                        <article key={key} className={`landing-case${index === 0 ? " landing-case-featured" : ""}`}>
                            <p className="landing-case-meta">{t(`landing.cases.${key}.meta`)}</p>
                            <h2 className="landing-case-title">
                                <Link href={`/case-studies/${key}`} className="landing-case-link">
                                    {t(`landing.cases.${key}.title`)}
                                </Link>
                            </h2>
                            <p className="landing-case-body">{t(`landing.cases.${key}.body`)}</p>
                            <p className="landing-case-outcome">
                                <span className="landing-case-outcome-label">{t("landing.cases.outcome_label")}</span>
                                {t(`landing.cases.${key}.outcome`)}
                            </p>
                            <Link className="landing-text-link" href={`/case-studies/${key}`}>
                                {t("landing.cases.read_more")}
                                <i className="bi bi-arrow-left" aria-hidden="true" />
                            </Link>
                        </article>
                    ))}
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-inline-cta">
                    <p>{t("landing.cases.cta_body")}</p>
                    <Link className="btn landing-btn landing-btn-accent" href="/contact">
                        {t("landing.cases.cta")}
                    </Link>
                </div>
            </section>
        </LandingLayout>
    );
}
