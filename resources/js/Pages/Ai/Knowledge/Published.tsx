import { Link, router } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EmptyState } from "../../../Components/Feedback/EmptyState";
import { FilterChips, Pager, topicLabel, withQuery, type PaginatedProps } from "../../../Components/Ai/aiShared";
import { useT } from "../../../Hooks/useT";

export type ArticleRow = {
    id: string;
    title: string;
    body: string;
    topic: string;
    version: number;
    effective_from: string | null;
    effective_until: string | null;
};

type Props = {
    articles: PaginatedProps<ArticleRow>;
    topics: string[];
    filters: { topic?: string | null; search?: string | null };
    can_manage: boolean;
    ai_available: boolean;
};

const BASE = "/ai/pages/knowledge";

export default function Published({ articles, topics, filters, can_manage, ai_available }: Props) {
    const t = useT();
    const [search, setSearch] = useState(filters.search ?? "");
    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(BASE, { topic: filters.topic || undefined, search: search.trim() || undefined }, { preserveState: true, preserveScroll: true });
    };
    const filtered = Boolean(filters.topic || filters.search);

    return (
        <AppLayout title={t("nav.ai.knowledge", undefined, "قاعدة المعرفة")}>
            <div className="app-page d-flex flex-column gap-3">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title={t("nav.ai.knowledge", undefined, "قاعدة المعرفة")}
                        description={t("ai.knowledge.published_description", undefined, "مقالات معتمدة من فريق بيكون يستند إليها المساعد.")}
                    />
                    <div className="d-flex flex-wrap gap-2">
                        {ai_available ? (
                            <Link href="/ai/pages/assistant" className="btn btn-primary">
                                <i className="bi bi-stars me-2" aria-hidden="true" />
                                {t("ai.assistant.ask", undefined, "اسأل المساعد")}
                            </Link>
                        ) : null}
                        {can_manage ? (
                            <Link href="/ai/admin/knowledge/page" className="btn btn-outline-secondary">
                                {t("nav.admin.knowledge", undefined, "إدارة المقالات")}
                            </Link>
                        ) : null}
                    </div>
                </div>
                <form role="search" onSubmit={submit} className="app-search-toolbar d-flex gap-2">
                    <label htmlFor="kb-search" className="visually-hidden">
                        {t("ai.knowledge.search", undefined, "ابحث في المقالات")}
                    </label>
                    <input
                        id="kb-search"
                        type="search"
                        className="form-control"
                        placeholder={t("ai.knowledge.search", undefined, "ابحث في المقالات")}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <button type="submit" className="btn btn-primary">
                        {t("common.search", undefined, "بحث")}
                    </button>
                </form>
                <FilterChips
                    label={t("ai.knowledge.filter_topic", undefined, "تصفية حسب الموضوع")}
                    current={filters.topic ?? null}
                    options={[
                        { value: null, label: t("ai.knowledge.all_topics", undefined, "كل المواضيع") },
                        ...topics.map((topic) => ({ value: topic, label: topicLabel(topic) })),
                    ]}
                    hrefFor={(v) => withQuery(BASE, { topic: v, search: filters.search })}
                />
                {articles.data.length === 0 ? (
                    <SectionCard>
                        <EmptyState
                            icon="bi-journal-text"
                            title={
                                filtered
                                    ? t("ai.knowledge.empty_filtered", undefined, "لا توجد مقالات مطابقة")
                                    : t("ai.knowledge.empty", undefined, "لا توجد مقالات منشورة بعد")
                            }
                            description={
                                filtered
                                    ? t("ai.knowledge.empty_filtered_hint", undefined, "جرّب كلمة بحث أو موضوعاً آخر.")
                                    : t("ai.knowledge.empty_hint", undefined, "ستظهر هنا المقالات بعد اعتمادها من فريق بيكون.")
                            }
                        />
                    </SectionCard>
                ) : (
                    <div className="d-flex flex-column gap-3">
                        {articles.data.map((a) => (
                            <article key={a.id} className="app-section">
                                <details>
                                    <summary className="app-section__header d-flex justify-content-between align-items-center gap-2" style={{ cursor: "pointer" }}>
                                        <h2 className="mb-0">{a.title}</h2>
                                        <span className="app-status app-status--neutral flex-shrink-0">{topicLabel(a.topic)}</span>
                                    </summary>
                                    <div className="app-section__body">
                                        <p className="mb-2" style={{ whiteSpace: "pre-line" }}>
                                            {a.body}
                                        </p>
                                        <p className="small text-body-secondary mb-0">
                                            {t("ai.knowledge.version", undefined, "الإصدار")} <bdi dir="ltr">{a.version}</bdi>
                                            {a.effective_from ? (
                                                <>
                                                    {" · "}
                                                    {t("ai.knowledge.effective_from", undefined, "ساري من")}{" "}
                                                    <bdi dir="ltr">{a.effective_from}</bdi>
                                                </>
                                            ) : null}
                                            {a.effective_until ? (
                                                <>
                                                    {" "}
                                                    {t("ai.knowledge.effective_until", undefined, "حتى")}{" "}
                                                    <bdi dir="ltr">{a.effective_until}</bdi>
                                                </>
                                            ) : null}
                                        </p>
                                    </div>
                                </details>
                            </article>
                        ))}
                        <Pager meta={articles.meta} links={articles.links} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
