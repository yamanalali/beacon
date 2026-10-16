import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";

type Log = {
    id: number;
    created_at: string | null;
    action: string;
    target_type: string;
    target_id: string;
    actor_name: string | null;
    actor_email: string | null;
    context_json: string;
};

export default function Show({ log }: { log: Log }) {
    return (
        <AppLayout title="تفاصيل السجل">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={`سجل رقم ${log.id}`} description="للقراءة فقط." />
                <div>
                    <Link href="/admin/audit-logs" className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                        العودة إلى سجل التدقيق
                    </Link>
                </div>
                <SectionCard title="البيانات">
                    <dl className="row mb-0">
                        <dt className="col-sm-3">الوقت</dt>
                        <dd className="col-sm-9" dir="ltr">{log.created_at}</dd>
                        <dt className="col-sm-3">المستخدم</dt>
                        <dd className="col-sm-9">
                            {log.actor_name ?? "النظام"} {log.actor_email ? <span dir="ltr">({log.actor_email})</span> : null}
                        </dd>
                        <dt className="col-sm-3">الإجراء</dt>
                        <dd className="col-sm-9" dir="ltr">{log.action}</dd>
                        <dt className="col-sm-3">السجل المتأثر</dt>
                        <dd className="col-sm-9" dir="ltr">
                            {log.target_type} #{log.target_id}
                        </dd>
                    </dl>
                </SectionCard>
                <SectionCard title="تفاصيل التغيير (قبل / بعد)">
                    <pre dir="ltr" className="mb-0 small" style={{ whiteSpace: "pre-wrap", wordBreak: "break-word" }}>
                        {log.context_json}
                    </pre>
                </SectionCard>
            </div>
        </AppLayout>
    );
}
