export const topicLabels: Record<string, string> = {
    bookkeeping: "مسك الدفاتر",
    platform_use: "استخدام المنصة",
    business_planning: "تخطيط الأعمال",
    compliance: "الامتثال",
    financial_readiness: "الجاهزية المالية",
};
export const statusLabels: Record<string, string> = {
    draft: "مسودة",
    published: "منشور",
    archived: "مؤرشف",
    open: "مفتوح",
};
export const displayDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat("ar", { dateStyle: "medium" }).format(
              new Date(value),
          )
        : "—";
export type Article = {
    id: string;
    title: string;
    body: string;
    topic: string;
    version: number;
    effective_from: string | null;
    effective_until: string | null;
    status?: string;
    published_at?: string | null;
    updated_at?: string | null;
};
export type Escalation = {
    id: string;
    question: string;
    status: string;
    created_at: string | null;
    enterprise_id?: string | null;
};
