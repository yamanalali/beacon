import { Link, router } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import { topicLabel, type PaginatedProps } from "../../Components/Ai/aiShared";
import { LandingLayout } from "../../Layouts/LandingLayout";
import { useT } from "../../Hooks/useT";

type ArticleSummary = {
    id: string;
    title: string;
    excerpt: string;
    topic: string;
};

type Props = {
    articles: PaginatedProps<ArticleSummary>;
    topics: string[];
    filters: { topic?: string | null; search?: string | null };
};

export default function Knowledge({ articles, topics, filters }: Props) {
    const t = useT();
    const [search, setSearch] = useState(filters.search ?? "");
    const filtered = Boolean(filters.topic || filters.search);

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(
            "/knowledge",
            {
                topic: filters.topic || undefined,
                search: search.trim() || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function topicHref(topic: string | null) {
        const params = new URLSearchParams();
        if (topic) {
            params.set("topic", topic);
        }
        if (filters.search) {
            params.set("search", filters.search);
        }
        const q = params.toString();
        return q ? `/knowledge?${q}` : "/knowledge";
    }

    return (
        <LandingLayout title={`${t("landing.nav.knowledge")} — ${t("common.app_name")}`}>
            <section className="landing-page-hero">
                <div className="landing-container">
                    <p className="landing-eyebrow">{t("landing.knowledge.eyebrow")}</p>
                    <h1 className="landing-page-title">{t("landing.knowledge.title")}</h1>
                    <p className="landing-page-lede">{t("landing.knowledge.lede")}</p>
                </div>
            </section>

            <section className="landing-section">
                <div className="landing-container">
                    <form role="search" className="landing-knowledge-toolbar" onSubmit={submit}>
                        <label htmlFor="landing-kb-search" className="visually-hidden">
                            {t("landing.knowledge.search")}
                        </label>
                        <input
                            id="landing-kb-search"
                            type="search"
                            className="form-control landing-knowledge-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t("landing.knowledge.search")}
                        />
                        <button type="submit" className="btn landing-btn landing-btn-accent">
                            {t("common.search")}
                        </button>
                    </form>

                    <div className="landing-knowledge-filters" role="group" aria-label={t("landing.knowledge.filter_topic")}>
                        <Link href={topicHref(null)} className={`landing-chip${!filters.topic ? " is-active" : ""}`}>
                            {t("landing.knowledge.all_topics")}
                        </Link>
                        {topics.map((topic) => (
                            <Link
                                key={topic}
                                href={topicHref(topic)}
                                className={`landing-chip${filters.topic === topic ? " is-active" : ""}`}
                            >
                                {topicLabel(topic, t)}
                            </Link>
                        ))}
                    </div>

                    {articles.data.length === 0 ? (
                        <div className="landing-empty">
                            <h2 className="landing-empty-title">
                                {filtered ? t("landing.knowledge.empty_filtered") : t("landing.knowledge.empty")}
                            </h2>
                            <p className="landing-prose">
                                {filtered ? t("landing.knowledge.empty_filtered_hint") : t("landing.knowledge.empty_hint")}
                            </p>
                        </div>
                    ) : (
                        <ul className="landing-knowledge-list">
                            {articles.data.map((article) => (
                                <li key={article.id} className="landing-knowledge-item">
                                    <div>
                                        <h2 className="landing-knowledge-title">
                                            <Link href={`/knowledge/${article.id}`} className="landing-case-link">
                                                {article.title}
                                            </Link>
                                        </h2>
                                        <p className="landing-knowledge-body">{article.excerpt}</p>
                                        <Link className="landing-text-link" href={`/knowledge/${article.id}`}>
                                            {t("landing.knowledge.read_more")}
                                            <i className="bi bi-arrow-left" aria-hidden="true" />
                                        </Link>
                                    </div>
                                    <span className="landing-knowledge-tag">{topicLabel(article.topic, t)}</span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {articles.meta.last_page > 1 ? (
                        <nav className="landing-pager" aria-label={t("common.more")}>
                            {articles.links.prev ? (
                                <Link href={articles.links.prev} className="landing-pager-link">
                                    {t("common.back")}
                                </Link>
                            ) : null}
                            <span className="landing-pager-status">
                                {articles.meta.current_page} / {articles.meta.last_page}
                            </span>
                            {articles.links.next ? (
                                <Link href={articles.links.next} className="landing-pager-link">
                                    {t("common.next")}
                                </Link>
                            ) : null}
                        </nav>
                    ) : null}

                    <p className="landing-knowledge-note">{t("landing.knowledge.note")}</p>
                </div>
            </section>

            <section className="landing-section landing-section-paper">
                <div className="landing-container landing-inline-cta">
                    <p>{t("landing.knowledge.cta_body")}</p>
                    <Link className="btn landing-btn landing-btn-accent" href="/login">
                        {t("landing.hero.cta_login")}
                    </Link>
                </div>
            </section>
        </LandingLayout>
    );
}
