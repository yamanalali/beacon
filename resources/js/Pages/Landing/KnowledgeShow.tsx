import { Link } from "@inertiajs/react";
import { topicLabel } from "../../Components/Ai/aiShared";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

type Article = {
    id: string;
    title: string;
    body: string;
    topic: string;
    version: number;
    effective_from: string | null;
    effective_until: string | null;
    published_at: string | null;
};

type Related = {
    id: string;
    title: string;
    excerpt: string;
    topic: string;
};

type Props = {
    article: Article;
    related: Related[];
};

export default function KnowledgeShow({ article, related }: Props) {
    const t = useT();

    return (
        <LandingLayout title={`${article.title} — ${t("common.app_name")}`}>
            <section className="landing-page-hero">
                <div className="landing-container">
                    <p className="landing-eyebrow">
                        <Link href="/knowledge" className="landing-crumb">
                            {t("landing.nav.knowledge")}
                        </Link>
                        <span aria-hidden="true"> · </span>
                        {topicLabel(article.topic, t)}
                    </p>
                    <h1 className="landing-page-title landing-page-title-wide">{article.title}</h1>
                    <p className="landing-page-lede landing-knowledge-meta">
                        {t("landing.knowledge.version")} <bdi dir="ltr">{article.version}</bdi>
                        {article.effective_from ? (
                            <>
                                {" · "}
                                {t("landing.knowledge.effective_from")} {article.effective_from}
                            </>
                        ) : null}
                    </p>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container landing-detail">
                    <article className="landing-detail-main">
                        <div className="landing-article-body">{article.body}</div>
                        <p className="landing-detail-note">{t("landing.knowledge.note")}</p>
                        <Link className="landing-text-link" href="/knowledge">
                            {t("landing.knowledge.back")}
                        </Link>
                    </article>

                    <aside className="landing-detail-aside">
                        <div className="landing-detail-card">
                            <h2 className="landing-detail-heading">{t("landing.knowledge.related_title")}</h2>
                            {related.length === 0 ? (
                                <p className="landing-prose">{t("landing.knowledge.related_empty")}</p>
                            ) : (
                                <ul className="landing-detail-related">
                                    {related.map((item) => (
                                        <li key={item.id}>
                                            <Link href={`/knowledge/${item.id}`}>{item.title}</Link>
                                            <span>{topicLabel(item.topic, t)}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <Link className="btn landing-btn landing-btn-accent" href="/login">
                                {t("landing.hero.cta_login")}
                            </Link>
                        </div>
                    </aside>
                </div>
            </section>
        </LandingLayout>
    );
}
