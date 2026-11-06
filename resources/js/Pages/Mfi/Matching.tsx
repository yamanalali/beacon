import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";

type Result = { criterion_id: number; label: string; result: "met" | "not_met" | "needs_review"; result_label: string; reason: string };
type Match = {
    public_id: string;
    name_ar: string;
    name_en: string | null;
    code: string | null;
    region_ar: string | null;
    focus_ar: string | null;
    loan_size_min: string | number | null;
    loan_size_max: string | number | null;
    loan_currency: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    results: Result[];
    counts: { met: number; not_met: number; needs_review: number };
};
type Props = { enterprise: { public_id: string; business_name: string }; matches: Match[]; disclaimer: string };

const tone: Record<Result["result"], StatusTone> = { met: "success", not_met: "danger" as StatusTone, needs_review: "warning" };

function loanLabel(m: Match): string | null {
    if (m.loan_size_min == null && m.loan_size_max == null) return null;
    const currency = m.loan_currency ?? "SYP";
    const min = m.loan_size_min != null ? Number(m.loan_size_min).toLocaleString("ar") : "—";
    const max = m.loan_size_max != null ? Number(m.loan_size_max).toLocaleString("ar") : "—";
    return `${min} – ${max} ${currency}`;
}

export default function Matching({ enterprise, matches, disclaimer }: Props) {
    return (
        <AppLayout title="مطابقة مؤسسات التمويل">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="مطابقة مؤسسات التمويل" description={enterprise.business_name} />
                <div className="alert alert-info mb-0">{disclaimer}</div>
                <div className="d-flex flex-column gap-3">
                    {matches.length === 0 ? <EmptyState title="لا توجد مؤسسات فعّالة" /> : null}
                    {matches.map((m) => {
                        const loan = loanLabel(m);
                        return (
                            <article key={m.public_id} className="border rounded p-3 bg-white">
                                <div className="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <div>
                                        <strong className="d-block">
                                            {m.name_ar}
                                            {m.code ? <span className="text-body-secondary fw-normal small ms-2">({m.code})</span> : null}
                                        </strong>
                                        {m.name_en ? <span className="small text-body-secondary d-block" lang="en">{m.name_en}</span> : null}
                                        <ul className="list-unstyled small text-body-secondary mb-0 mt-1">
                                            {m.region_ar ? <li>التغطية: {m.region_ar}</li> : null}
                                            {loan ? <li>نطاق القرض: {loan}</li> : null}
                                            {m.focus_ar ? <li>{m.focus_ar}</li> : null}
                                            {m.contact_name || m.contact_phone ? (
                                                <li>التواصل: {[m.contact_name, m.contact_phone].filter(Boolean).join(" · ")}</li>
                                            ) : null}
                                        </ul>
                                    </div>
                                    <p className="small mb-0 text-body-secondary">
                                        مستوفى {m.counts.met} · غير مستوفى {m.counts.not_met} · يحتاج مراجعة {m.counts.needs_review}
                                    </p>
                                </div>
                                <ul className="list-unstyled mb-0">
                                    {m.results.map((r) => (
                                        <li key={r.criterion_id} className="border-top pt-2 mt-2">
                                            <div className="d-flex justify-content-between gap-2">
                                                <span>{r.label}</span>
                                                <StatusBadge label={r.result_label} tone={tone[r.result]} />
                                            </div>
                                            <small className="text-muted">{r.reason}</small>
                                        </li>
                                    ))}
                                    {m.results.length === 0 ? (
                                        <li className="border-top pt-2 mt-2 small text-body-secondary">لا توجد شروط أهلية مفعّلة لهذه المؤسسة بعد.</li>
                                    ) : null}
                                </ul>
                            </article>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
