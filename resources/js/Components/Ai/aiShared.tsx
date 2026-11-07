import { Link } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import { StatusBadge, type StatusTone } from "../Feedback/StatusBadge";

export type PaginatedProps<T> = {
    data: T[];
    links: Record<string, string | null>;
    meta: { current_page: number; last_page: number; per_page?: number; total: number; from: number | null; to: number | null; path?: string; links?: { url: string | null; label: string; active: boolean }[] };
};

const TOPIC_KEYS: Record<string, string> = {
    bookkeeping: "ai.topic.bookkeeping",
    platform_use: "ai.topic.platform_use",
    business_planning: "ai.topic.business_planning",
    compliance: "ai.topic.compliance",
    financial_readiness: "ai.topic.financial_readiness",
};

const TOPIC_FB: Record<string, string> = {
    bookkeeping: "مسك الدفاتر",
    platform_use: "استخدام المنصة",
    business_planning: "تخطيط الأعمال",
    compliance: "الامتثال",
    financial_readiness: "الجاهزية المالية",
};

/** @deprecated use topicLabel with translations */
export const TOPIC_LABELS: Record<string, string> = TOPIC_FB;

export const topicLabel = (key: string, tr?: ReturnType<typeof useT>) => {
    const k = TOPIC_KEYS[key];
    if (!k) return key;
    const fb = TOPIC_FB[key] ?? key;
    return tr ? tr(k, undefined, fb) : fb;
};

function escalationLabel(status: string, t: ReturnType<typeof useT>): [string, StatusTone] {
    const map: Record<string, [string, StatusTone]> = {
        open: [t("ai.escalation.status.open", undefined, "مفتوح"), "warning"],
        answered: [t("ai.escalation.status.answered", undefined, "تمت الإجابة"), "success"],
        closed: [t("ai.escalation.status.closed", undefined, "مغلق"), "neutral"],
    };
    return map[status] ?? [status, "neutral"];
}

export function EscalationStatus({ status }: { status: string }) {
    const t = useT();
    const [label, tone] = escalationLabel(status, t);
    return <StatusBadge label={label} tone={tone} />;
}

function articleLabel(status: string, t: ReturnType<typeof useT>): [string, StatusTone] {
    const map: Record<string, [string, StatusTone]> = {
        draft: [t("ai.knowledge.status.draft", undefined, "مسودة"), "neutral"],
        published: [t("ai.knowledge.status.published", undefined, "منشور"), "success"],
        archived: [t("ai.knowledge.status.archived", undefined, "مؤرشف"), "warning"],
    };
    return map[status] ?? [status, "neutral"];
}

export function ArticleStatus({ status }: { status: string }) {
    const t = useT();
    const [label, tone] = articleLabel(status, t);
    return <StatusBadge label={label} tone={tone} />;
}

export const articleStatusLabel = (s: string, tr?: ReturnType<typeof useT>) => {
    if (tr) return articleLabel(s, tr)[0];
    const fb: Record<string, string> = { draft: "مسودة", published: "منشور", archived: "مؤرشف" };
    return fb[s] ?? s;
};

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
    const t = useT();
    if (meta.last_page <= 1) return null;
    return (
        <nav aria-label={t("pagination.nav_aria", undefined, "التنقل بين الصفحات")} className="d-flex justify-content-between align-items-center gap-2 mt-3">
            {links.prev ? (
                <Link href={links.prev} className="btn btn-outline-secondary btn-sm" preserveScroll>
                    {t("pagination.previous", undefined, "السابق")}
                </Link>
            ) : (
                <span />
            )}
            <span className="small text-body-secondary">
                {t("pagination.page", undefined, "صفحة")}{" "}
                <bdi dir="ltr">{meta.current_page}</bdi> {t("pagination.of", undefined, "من")}{" "}
                <bdi dir="ltr">{meta.last_page}</bdi>
            </span>
            {links.next ? (
                <Link href={links.next} className="btn btn-outline-secondary btn-sm" preserveScroll>
                    {t("pagination.next", undefined, "التالي")}
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
