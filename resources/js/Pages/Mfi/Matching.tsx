import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";

type Result = { criterion_id: number; label: string; result: "met" | "not_met" | "needs_review"; result_label: string; reason: string };
type Match = { public_id: string; name_ar: string; results: Result[]; counts: { met: number; not_met: number; needs_review: number } };
type Props = { enterprise: { public_id: string; business_name: string }; matches: Match[]; disclaimer: string };

const tone: Record<Result["result"], StatusTone> = { met: "success", not_met: "danger" as StatusTone, needs_review: "warning" };

export default function Matching({ enterprise, matches, disclaimer }: Props) {
    return (
        <AppLayout title="مطابقة مؤسسات التمويل">
            <div className="app-page">
                <PageHeader title="مطابقة مؤسسات التمويل" description={enterprise.business_name} />
                <div className="alert alert-info">{disclaimer}</div>
                <div className="d-flex flex-column gap-3">
                    {matches.length === 0 ? <EmptyState title="لا توجد مؤسسات فعّالة" /> : null}
                    {matches.map((m) => (
                        <article key={m.public_id} className="border rounded p-3 bg-white">
                            <strong>{m.name_ar}</strong>
                            <p className="mb-2">مستوفى {m.counts.met} - غير مستوفى {m.counts.not_met} - يحتاج مراجعة {m.counts.needs_review}</p>
                            <ul className="list-unstyled mb-0">
                                {m.results.map((r) => (
                                    <li key={r.criterion_id} className="border-top pt-2 mt-2">
                                        <div className="d-flex justify-content-between gap-2"><span>{r.label}</span><StatusBadge label={r.result_label} tone={tone[r.result]} /></div>
                                        <small className="text-muted">{r.reason}</small>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
