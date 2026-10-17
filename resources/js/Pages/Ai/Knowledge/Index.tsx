import { router } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { DataTable, type Column } from "../../../Components/Data/DataTable";
import { ArticleStatus, FilterChips, articleStatusLabel, shortDate, topicLabel, withQuery, type PaginatedProps } from "../../../Components/Ai/aiShared";
import { postJson } from "../../../Components/Ai/http";
import type { ArticleRow } from "./Published";

export type AdminArticleRow = ArticleRow & { status: string; published_at: string | null; updated_at: string | null };

type Props = {
    articles: PaginatedProps<AdminArticleRow>;
    topics: string[];
    statuses: string[];
    filters: { status?: string | null; topic?: string | null };
    can_manage: boolean;
    fetchImpl?: typeof fetch;
};

type FormState = { id: string | null; title: string; body: string; topic: string; effective_from: string; effective_until: string };
const BASE = "/ai/admin/knowledge";
const PAGE = "/ai/admin/knowledge/page";

export default function Index({ articles, topics, statuses, filters, can_manage, fetchImpl }: Props) {
    const empty: FormState = { id: null, title: "", body: "", topic: topics[0] ?? "", effective_from: "", effective_until: "" };
    const [form, setForm] = useState<FormState | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [message, setMessage] = useState<{ tone: "success" | "danger"; text: string } | null>(null);
    const [busy, setBusy] = useState<string | null>(null);

    const reload = () => router.reload({ only: ["articles"] });

    async function act(a: AdminArticleRow, action: "publish" | "archive") {
        setBusy(`${action}:${a.id}`);
        const r = await postJson(`${BASE}/${a.id}/${action}`, null, fetchImpl);
        setBusy(null);
        setMessage(r.ok ? { tone: "success", text: action === "publish" ? "تم نشر المقال." : "تمت أرشفة المقال." } : { tone: "danger", text: r.message });
        if (r.ok) reload();
    }

    async function save(e: FormEvent) {
        e.preventDefault();
        if (!form) return;
        setBusy("save");
        setErrors({});
        const payload = { title: form.title, body: form.body, topic: form.topic, effective_from: form.effective_from || null, effective_until: form.effective_until || null };
        const r = form.id ? await postJson(`${BASE}/${form.id}`, payload, fetchImpl, "PUT") : await postJson(BASE, payload, fetchImpl);
        setBusy(null);
        if (r.ok) {
            setForm(null);
            setMessage({ tone: "success", text: form.id ? "تم حفظ التعديلات. المقال يعود مسودة حتى يُعاد نشره." : "تم إنشاء المسودة." });
            reload();
        } else {
            setErrors(r.errors ?? {});
            setMessage({ tone: "danger", text: r.message });
        }
    }

    const columns: Column<AdminArticleRow>[] = [
        { key: "title", header: "العنوان", primary: true, cell: (a) => <span className="fw-semibold">{a.title}</span> },
        { key: "topic", header: "الموضوع", cell: (a) => topicLabel(a.topic) },
        { key: "status", header: "الحالة", cell: (a) => <ArticleStatus status={a.status} /> },
        { key: "version", header: "الإصدار", numeric: true, hideOnMobile: true, cell: (a) => <bdi dir="ltr">{a.version}</bdi> },
        { key: "updated_at", header: "آخر تحديث", numeric: true, hideOnMobile: true, cell: (a) => <bdi dir="ltr">{shortDate(a.updated_at)}</bdi> },
        {
            key: "actions",
            header: "",
            hideable: false,
            cell: (a) =>
                can_manage ? (
                    <div className="d-flex flex-wrap gap-2">
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => { setForm({ id: a.id, title: a.title, body: a.body, topic: a.topic, effective_from: a.effective_from ?? "", effective_until: a.effective_until ?? "" }); setErrors({}); }}>
                            تعديل
                        </button>
                        {a.status !== "published" ? (
                            <button type="button" className="btn btn-sm btn-primary" disabled={busy !== null} onClick={() => act(a, "publish")}>
                                نشر
                            </button>
                        ) : null}
                        {a.status !== "archived" ? (
                            <button type="button" className="btn btn-sm btn-outline-secondary" disabled={busy !== null} onClick={() => act(a, "archive")}>
                                أرشفة
                            </button>
                        ) : null}
                    </div>
                ) : null,
        },
    ];

    const err = (k: string) => errors[k]?.[0];
    const field = (k: keyof FormState, v: string) => setForm((f) => (f ? { ...f, [k]: v } : f));

    return (
        <AppLayout title="إدارة قاعدة المعرفة">
            <div className="app-page d-flex flex-column gap-3">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader title="إدارة قاعدة المعرفة" description="المسودات والمقالات المنشورة. أي تعديل في المحتوى يعيد المقال مسودة حتى يُعتمد." />
                    {can_manage && !form ? (
                        <button type="button" className="btn btn-primary" onClick={() => { setForm(empty); setErrors({}); }}>
                            <i className="bi bi-plus-lg me-2" aria-hidden="true" />
                            مقال جديد
                        </button>
                    ) : null}
                </div>
                {message ? (
                    <div className={`alert alert-${message.tone} mb-0`} role={message.tone === "danger" ? "alert" : "status"}>
                        {message.text}
                    </div>
                ) : null}
                {form ? (
                    <SectionCard title={form.id ? "تعديل المقال" : "مقال جديد"}>
                        <form onSubmit={save} noValidate className="row g-3">
                            <div className="col-12 col-lg-8">
                                <label htmlFor="kb-title" className="form-label">العنوان</label>
                                <input id="kb-title" className={`form-control ${err("title") ? "is-invalid" : ""}`} value={form.title} onChange={(e) => field("title", e.target.value)} maxLength={255} required />
                                {err("title") ? <div className="invalid-feedback">{err("title")}</div> : null}
                            </div>
                            <div className="col-12 col-lg-4">
                                <label htmlFor="kb-topic" className="form-label">الموضوع</label>
                                <select id="kb-topic" className={`form-select ${err("topic") ? "is-invalid" : ""}`} value={form.topic} onChange={(e) => field("topic", e.target.value)}>
                                    {topics.map((t) => (
                                        <option key={t} value={t}>{topicLabel(t)}</option>
                                    ))}
                                </select>
                                {err("topic") ? <div className="invalid-feedback">{err("topic")}</div> : null}
                            </div>
                            <div className="col-12">
                                <label htmlFor="kb-body" className="form-label">المحتوى</label>
                                <textarea id="kb-body" rows={8} className={`form-control ${err("body") ? "is-invalid" : ""}`} value={form.body} onChange={(e) => field("body", e.target.value)} maxLength={20000} required />
                                {err("body") ? <div className="invalid-feedback">{err("body")}</div> : null}
                            </div>
                            <div className="col-6 col-lg-3">
                                <label htmlFor="kb-from" className="form-label">ساري من</label>
                                <input id="kb-from" type="date" className="form-control" value={form.effective_from} onChange={(e) => field("effective_from", e.target.value)} />
                            </div>
                            <div className="col-6 col-lg-3">
                                <label htmlFor="kb-until" className="form-label">حتى</label>
                                <input id="kb-until" type="date" className={`form-control ${err("effective_until") ? "is-invalid" : ""}`} value={form.effective_until} onChange={(e) => field("effective_until", e.target.value)} />
                                {err("effective_until") ? <div className="invalid-feedback">{err("effective_until")}</div> : null}
                            </div>
                            <div className="col-12 d-grid d-sm-flex gap-2">
                                <button type="submit" className="btn btn-primary" disabled={busy === "save"}>حفظ كمسودة</button>
                                <button type="button" className="btn btn-outline-secondary" onClick={() => setForm(null)}>إلغاء</button>
                            </div>
                        </form>
                    </SectionCard>
                ) : null}
                <div className="d-flex flex-column gap-2">
                    <FilterChips
                        label="تصفية حسب الحالة"
                        current={filters.status ?? null}
                        options={[{ value: null, label: "كل الحالات" }, ...statuses.map((s) => ({ value: s, label: articleStatusLabel(s) }))]}
                        hrefFor={(v) => withQuery(PAGE, { status: v, topic: filters.topic })}
                    />
                    <FilterChips
                        label="تصفية حسب الموضوع"
                        current={filters.topic ?? null}
                        options={[{ value: null, label: "كل المواضيع" }, ...topics.map((t) => ({ value: t, label: topicLabel(t) }))]}
                        hrefFor={(v) => withQuery(PAGE, { status: filters.status, topic: v })}
                    />
                </div>
                <SectionCard title="المقالات">
                    <DataTable
                        caption="مقالات قاعدة المعرفة"
                        columns={columns}
                        rowKey={(a) => a.id}
                        emptyTitle="لا توجد مقالات"
                        emptyDescription="أنشئ مقالاً جديداً ثم انشره ليستخدمه المساعد."
                        server={{ paginator: articles, only: ["articles"], query: { status: filters.status ?? undefined, topic: filters.topic ?? undefined } }}
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}
