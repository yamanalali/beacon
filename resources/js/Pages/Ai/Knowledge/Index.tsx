import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { DataTable, type Paginator } from "../../../Components/Data/DataTable";
import { FilterBar } from "../../../Components/Filters/FilterBar";
import { SelectFilter } from "../../../Components/Filters/SelectFilter";
import { StatusBadge } from "../../../Components/Feedback/StatusBadge";
import { useJsonAction } from "../../../hooks/useJsonAction";
import { topicLabels, statusLabels, type Article } from "../shared";

type Draft = {
    id: string | null;
    title: string;
    body: string;
    topic: string;
    effective_from: string;
    effective_until: string;
};
export default function Index({
    articles,
    topics,
    statuses,
    filters,
    can_manage,
}: {
    articles: Paginator<Article>;
    topics: string[];
    statuses: string[];
    filters: { status: string | null; topic: string | null };
    can_manage: boolean;
}) {
    const [draft, setDraft] = useState<Draft | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const { request, busy, error } = useJsonAction();
    const reload = () => router.reload({ only: ["articles"] });
    const field = (key: keyof Draft, value: string) =>
        setDraft((d) => (d ? { ...d, [key]: value } : d));
    return (
        <AppLayout title="إدارة المعرفة">
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title="إدارة المعرفة"
                        description="أنشئ الإرشادات وراجعها قبل نشرها للمستخدمين."
                    />
                    {can_manage ? (
                        <button
                            className="btn btn-primary"
                            disabled={busy}
                            onClick={() => {
                                setDraft({
                                    id: null,
                                    title: "",
                                    body: "",
                                    topic: topics[0] ?? "bookkeeping",
                                    effective_from: "",
                                    effective_until: "",
                                });
                                setSuccess(null);
                            }}
                        >
                            إرشاد جديد
                        </button>
                    ) : null}
                </div>
                <Link href="/ai/pages/knowledge" className="btn btn-light mb-3">
                    عرض الدليل المنشور
                </Link>
                {error ? (
                    <div className="alert alert-danger" role="alert">
                        {error}
                    </div>
                ) : null}
                {success ? (
                    <div className="alert alert-success" role="status">
                        {success}
                    </div>
                ) : null}
                {draft ? (
                    <div className="mb-3">
                        <SectionCard
                            title={draft.id ? "تعديل الإرشاد" : "إرشاد جديد"}
                        >
                            <form
                                onSubmit={async (e) => {
                                    e.preventDefault();
                                    const { id, ...fields } = draft;
                                    const result = await request<Article>(
                                        id ? "put" : "post",
                                        `/ai/admin/knowledge${id ? `/${id}` : ""}`,
                                        {
                                            ...fields,
                                            effective_from:
                                                fields.effective_from || null,
                                            effective_until:
                                                fields.effective_until || null,
                                        },
                                    );
                                    if (result) {
                                        setDraft(null);
                                        setSuccess(
                                            result.status === "draft"
                                                ? "تم حفظ الإرشاد كمسودة. راجعه ثم انشره عند جاهزيته."
                                                : "تم حفظ التعديلات.",
                                        );
                                        reload();
                                    }
                                }}
                            >
                                <div className="mb-3">
                                    <label
                                        htmlFor="article-title"
                                        className="form-label"
                                    >
                                        العنوان
                                    </label>
                                    <input
                                        autoFocus
                                        id="article-title"
                                        className="form-control"
                                        required
                                        maxLength={255}
                                        value={draft.title}
                                        onChange={(e) =>
                                            field("title", e.target.value)
                                        }
                                    />
                                </div>
                                <div className="mb-3">
                                    <label
                                        htmlFor="article-topic"
                                        className="form-label"
                                    >
                                        الموضوع
                                    </label>
                                    <select
                                        id="article-topic"
                                        className="form-select"
                                        value={draft.topic}
                                        onChange={(e) =>
                                            field("topic", e.target.value)
                                        }
                                    >
                                        {topics.map((t) => (
                                            <option key={t} value={t}>
                                                {topicLabels[t] ?? t}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-3">
                                    <label
                                        htmlFor="article-body"
                                        className="form-label"
                                    >
                                        نص الإرشاد
                                    </label>
                                    <textarea
                                        id="article-body"
                                        className="form-control"
                                        rows={8}
                                        required
                                        maxLength={20000}
                                        value={draft.body}
                                        onChange={(e) =>
                                            field("body", e.target.value)
                                        }
                                    />
                                </div>
                                <div className="row g-3 mb-3">
                                    <div className="col-12 col-md-6">
                                        <label
                                            htmlFor="effective-from"
                                            className="form-label"
                                        >
                                            ساري من (اختياري)
                                        </label>
                                        <input
                                            id="effective-from"
                                            type="date"
                                            className="form-control"
                                            value={draft.effective_from}
                                            onChange={(e) =>
                                                field(
                                                    "effective_from",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="col-12 col-md-6">
                                        <label
                                            htmlFor="effective-until"
                                            className="form-label"
                                        >
                                            ساري حتى (اختياري)
                                        </label>
                                        <input
                                            id="effective-until"
                                            type="date"
                                            className="form-control"
                                            min={
                                                draft.effective_from ||
                                                undefined
                                            }
                                            value={draft.effective_until}
                                            onChange={(e) =>
                                                field(
                                                    "effective_until",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <p className="small text-body-secondary">
                                    تعديل النص أو العنوان يعيد الإرشاد إلى
                                    المسودة للمراجعة قبل النشر.
                                </p>
                                <div className="d-flex gap-2">
                                    <button
                                        className="btn btn-primary"
                                        disabled={busy}
                                    >
                                        {busy ? "جارٍ الحفظ…" : "حفظ"}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-light"
                                        disabled={busy}
                                        onClick={() => setDraft(null)}
                                    >
                                        إلغاء
                                    </button>
                                </div>
                            </form>
                        </SectionCard>
                    </div>
                ) : null}
                <FilterBar
                    initial={{
                        status: filters.status ?? "",
                        topic: filters.topic ?? "",
                    }}
                >
                    {(v, set) => (
                        <>
                            <div className="col-12 col-lg-4">
                                <SelectFilter
                                    id="status"
                                    label="الحالة"
                                    value={v.status}
                                    options={statuses.map((s) => ({
                                        value: s,
                                        label: statusLabels[s] ?? s,
                                    }))}
                                    onChange={(v) => set("status", v)}
                                />
                            </div>
                            <div className="col-12 col-lg-4">
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
                    <DataTable
                        columns={[
                            {
                                key: "title",
                                header: "الإرشاد",
                                primary: true,
                                cell: (a) => (
                                    <span className="text-break">
                                        {a.title}
                                    </span>
                                ),
                            },
                            {
                                key: "topic",
                                header: "الموضوع",
                                cell: (a) => topicLabels[a.topic] ?? a.topic,
                            },
                            {
                                key: "version",
                                header: "الإصدار",
                                cell: (a) => a.version,
                            },
                            {
                                key: "status",
                                header: "الحالة",
                                cell: (a) => (
                                    <StatusBadge
                                        label={
                                            statusLabels[a.status ?? "draft"]
                                        }
                                        tone={
                                            a.status === "published"
                                                ? "success"
                                                : "neutral"
                                        }
                                    />
                                ),
                            },
                            {
                                key: "actions",
                                header: "الإجراءات",
                                cell: (a) =>
                                    can_manage ? (
                                        <div className="d-flex flex-wrap gap-2">
                                            <button
                                                className="btn btn-sm btn-outline-primary"
                                                disabled={busy}
                                                onClick={() => {
                                                    setDraft({
                                                        id: a.id,
                                                        title: a.title,
                                                        body: a.body,
                                                        topic: a.topic,
                                                        effective_from:
                                                            a.effective_from ??
                                                            "",
                                                        effective_until:
                                                            a.effective_until ??
                                                            "",
                                                    });
                                                    setSuccess(null);
                                                    window.scrollTo({
                                                        top: 0,
                                                        behavior: "smooth",
                                                    });
                                                }}
                                            >
                                                تعديل
                                            </button>
                                            {a.status !== "published" ? (
                                                <button
                                                    className="btn btn-sm btn-outline-success"
                                                    disabled={busy}
                                                    onClick={async () => {
                                                        if (
                                                            await request(
                                                                "post",
                                                                `/ai/admin/knowledge/${a.id}/publish`,
                                                            )
                                                        ) {
                                                            setSuccess(
                                                                "تم نشر الإرشاد.",
                                                            );
                                                            reload();
                                                        }
                                                    }}
                                                >
                                                    نشر
                                                </button>
                                            ) : null}
                                            {a.status !== "archived" ? (
                                                <button
                                                    className="btn btn-sm btn-outline-secondary"
                                                    disabled={busy}
                                                    onClick={async () => {
                                                        if (
                                                            await request(
                                                                "post",
                                                                `/ai/admin/knowledge/${a.id}/archive`,
                                                            )
                                                        ) {
                                                            setSuccess(
                                                                "تمت أرشفة الإرشاد.",
                                                            );
                                                            reload();
                                                        }
                                                    }}
                                                >
                                                    أرشفة
                                                </button>
                                            ) : null}
                                        </div>
                                    ) : null,
                            },
                        ]}
                        rowKey={(a) => a.id}
                        server={{ paginator: articles, query: filters }}
                        emptyTitle="لا توجد إرشادات بهذه التصفية"
                        caption="إدارة الإرشادات"
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
