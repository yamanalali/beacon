import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EmptyState } from "../../../Components/Feedback/EmptyState";
import { FilterBar } from "../../../Components/Filters/FilterBar";
import { SelectFilter } from "../../../Components/Filters/SelectFilter";
import { Pagination } from "../../../Components/Data/Pagination";
import type { Paginator } from "../../../Components/Data/DataTable";
import { displayDate, topicLabels, type Article } from "../shared";

export default function Published({
    articles,
    topics,
    filters,
    can_manage,
}: {
    articles: Paginator<Article>;
    topics: string[];
    filters: { topic: string | null; search: string | null };
    can_manage: boolean;
}) {
    const links =
        "meta" in articles ? (articles.meta.links ?? []) : articles.links;
    return (
        <AppLayout title="دليل المعرفة">
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title="دليل المعرفة"
                        description="إرشادات معتمدة تساعدك في إدارة منشأتك."
                    />
                    {can_manage ? (
                        <Link
                            href="/ai/admin/knowledge/page"
                            className="btn btn-outline-primary"
                        >
                            إدارة المحتوى
                        </Link>
                    ) : null}
                </div>
                <FilterBar
                    initial={{
                        topic: filters.topic ?? "",
                        search: filters.search ?? "",
                    }}
                >
                    {(v, set) => (
                        <>
                            <div className="col-12 col-lg-6">
                                <label
                                    htmlFor="knowledge-search"
                                    className="form-label"
                                >
                                    بحث في الإرشادات
                                </label>
                                <input
                                    id="knowledge-search"
                                    className="form-control"
                                    value={v.search}
                                    onChange={(e) =>
                                        set("search", e.target.value)
                                    }
                                />
                            </div>
                            <div className="col-12 col-lg-3">
                                <SelectFilter
                                    id="topic"
                                    label="الموضوع"
                                    value={v.topic}
                                    options={topics.map((t) => ({
                                        value: t,
                                        label: topicLabels[t] ?? t,
                                    }))}
                                    onChange={(v) => set("topic", v)}
                                />
                            </div>
                        </>
                    )}
                </FilterBar>
                <SectionCard>
                    {articles.data.length ? (
                        <div className="d-flex flex-column gap-3">
                            {articles.data.map((a) => (
                                <details
                                    key={a.id}
                                    className="border rounded-3 p-3"
                                >
                                    <summary className="fw-semibold text-break">
                                        {a.title}
                                        <span className="d-block small fw-normal text-body-secondary mt-1">
                                            {topicLabels[a.topic] ?? a.topic} ·
                                            الإصدار {a.version}
                                        </span>
                                    </summary>
                                    <div className="pt-3">
                                        <p
                                            style={{
                                                whiteSpace: "pre-wrap",
                                                overflowWrap: "anywhere",
                                            }}
                                        >
                                            {a.body}
                                        </p>
                                        {a.effective_from ||
                                        a.effective_until ? (
                                            <p className="small text-body-secondary mb-0">
                                                ساري من{" "}
                                                {displayDate(a.effective_from)}{" "}
                                                إلى{" "}
                                                {displayDate(a.effective_until)}
                                            </p>
                                        ) : null}
                                    </div>
                                </details>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="لا توجد إرشادات مطابقة"
                            description="جرّب البحث بكلمات أخرى أو تغيير الموضوع."
                        />
                    )}
                    <Pagination links={links} />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
