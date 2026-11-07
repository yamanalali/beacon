import { Link } from "@inertiajs/react";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

type Props = {
    slug: string;
    cases: string[];
};

export default function CaseStudyShow({ slug, cases }: Props) {
    const t = useT();
    const title = t(`landing.cases.${slug}.title`);
    const others = cases.filter((key) => key !== slug);

    return (
        <LandingLayout title={`${title} — ${t("common.app_name")}`}>
            <section className="landing-page-hero">
                <div className="landing-container">
                    <p className="landing-eyebrow">
                        <Link href="/case-studies" className="landing-crumb">
                            {t("landing.nav.case_studies")}
                        </Link>
                        <span aria-hidden="true"> · </span>
                        {t(`landing.cases.${slug}.meta`)}
                    </p>
                    <h1 className="landing-page-title">{title}</h1>
                    <p className="landing-page-lede">{t(`landing.cases.${slug}.lede`)}</p>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-detail">
                    <div className="landing-detail-main">
                        <section className="landing-detail-block">
                            <h2 className="landing-detail-heading">{t("landing.cases.challenge_label")}</h2>
                            <p className="landing-prose">{t(`landing.cases.${slug}.challenge`)}</p>
                        </section>
                        <section className="landing-detail-block">
                            <h2 className="landing-detail-heading">{t("landing.cases.approach_label")}</h2>
                            <p className="landing-prose">{t(`landing.cases.${slug}.approach`)}</p>
                        </section>
                        <section className="landing-detail-block">
                            <h2 className="landing-detail-heading">{t("landing.cases.steps_label")}</h2>
                            <ol className="landing-detail-steps">
                                {[1, 2, 3].map((n) => (
                                    <li key={n}>{t(`landing.cases.${slug}.step_${n}`)}</li>
                                ))}
                            </ol>
                        </section>
                        <section className="landing-detail-block landing-detail-outcome">
                            <h2 className="landing-detail-heading">{t("landing.cases.outcome_heading")}</h2>
                            <p className="landing-prose">{t(`landing.cases.${slug}.outcome`)}</p>
                            <p className="landing-detail-note">{t("landing.cases.disclaimer")}</p>
                        </section>
                    </div>

                    <aside className="landing-detail-aside">
                        <div className="landing-detail-card">
                            <h2 className="landing-detail-heading">{t("landing.cases.more_title")}</h2>
                            <ul className="landing-detail-related">
                                {others.map((key) => (
                                    <li key={key}>
                                        <Link href={`/case-studies/${key}`}>{t(`landing.cases.${key}.title`)}</Link>
                                        <span>{t(`landing.cases.${key}.meta`)}</span>
                                    </li>
                                ))}
                            </ul>
                            <Link className="btn landing-btn landing-btn-accent" href="/contact">
                                {t("landing.cases.cta")}
                            </Link>
                        </div>
                    </aside>
                </div>
            </section>
        </LandingLayout>
    );
}
