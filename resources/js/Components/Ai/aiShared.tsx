import { Link } from "@inertiajs/react";
import { StatusBadge, type StatusTone } from "../Feedback/StatusBadge";

export type PaginatedProps<T> = {
    data: T[];
    links: Record<string, string | null>;
    meta: { current_page: number; last_page: number; per_page?: number; total: number; from: number | null; to: number | null; path?: string; links?: { url: string | null; label: string; active: boolean }[] };
};

export const TOPIC_LABELS: Record<string, string> = {
    bookkeeping: "مسك الدفاتر",
    platform_use: "استخدام المنصة",
    business_planning: "تخطيط الأعمال",
    compliance: "الامتثال",
    financial_readiness: "الجاهزية المالية",
};
export const topicLabel = (t: string) => TOPIC_LABELS[t] ?? t;

const ESCALATION: Record<string, [string, StatusTone]> = {
    open: ["مفتوح", "warning"],
    answered: ["تمت الإجابة", "success"],
    closed: ["مغلق", "neutral"],
};
export function EscalationStatus({ status }: { status: string }) {
    const [label, tone] = ESCALATION[status] ?? [status, "neutral"];
    return <StatusBadge label={label} tone={tone} />;
}

const ARTICLE: Record<string, [string, StatusTone]> = {
    draft: ["مسودة", "neutral"],
    published: ["منشور", "success"],
    archived: ["مؤرشف", "warning"],
};
export function ArticleStatus({ status }: { status: string }) {
    const [label, tone] = ARTICLE[status] ?? [status, "neutral"];
    return <StatusBadge label={label} tone={tone} />;
}
export const articleStatusLabel = (s: string) => ARTICLE[s]?.[0] ?? s;

/** ISO timestamp -> "YYYY-MM-DD HH:MM" without client time-zone math. */
export function shortDate(iso: string | null | undefined): string {
    if (!iso) return "—";
    return iso.replace("T", " ").slice(0, 16);
}

/** Filter chips: each is a Link that keeps the other query params. */
export function FilterChips({
    label,
    options,
    current,
    hrefFor,
}: {
    label: string;
    options: { value: string | null; label: string }[];
    current: string | null | undefined;
    hrefFor: (value: string | null) => string;
}) {
    return (
        <nav aria-label={label} className="d-flex flex-wrap gap-2">
            {options.map((o) => {
                const active = (current ?? null) === o.value;
                return (
                    <Link
                        key={o.value ?? "all"}
                        href={hrefFor(o.value)}
                        preserveScroll
                        className={`btn btn-sm ${active ? "btn-primary" : "btn-outline-secondary"}`}
                        aria-current={active ? "true" : undefined}
                    >
                        {o.label}
                    </Link>
                );
            })}
        </nav>
    );
}

export function withQuery(path: string, query: Record<string, string | null | undefined>): string {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(query)) if (v) q.set(k, v);
    const s = q.toString();
    return s ? `${path}?${s}` : path;
}

/** Simple prev/next pager for card lists (server pagination). */
export function Pager({ meta, links }: Pick<PaginatedProps<unknown>, "meta" | "links">) {
    if (meta.last_page <= 1) return null;
    return (
        <nav aria-label="التنقل بين الصفحات" className="d-flex justify-content-between align-items-center gap-2 mt-3">
            {links.prev ? (
                <Link href={links.prev} className="btn btn-outline-secondary btn-sm" preserveScroll>
                    السابق
                </Link>
            ) : (
                <span />
            )}
            <span className="small text-body-secondary">
                صفحة <bdi dir="ltr">{meta.current_page}</bdi> من <bdi dir="ltr">{meta.last_page}</bdi>
            </span>
            {links.next ? (
                <Link href={links.next} className="btn btn-outline-secondary btn-sm" preserveScroll>
                    التالي
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
