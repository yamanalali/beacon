import { Link, router } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EmptyState } from "../../../Components/Feedback/EmptyState";
import { SelectInput } from "../../../Components/Forms/SelectInput";
import { TextInput } from "../../../Components/Forms/TextInput";
import { Pagination, type PaginationLink } from "../../../Components/Navigation/Pagination";

type Row = {
    id: number;
    created_at: string | null;
    action: string;
    target_type: string;
    target_id: string;
    actor_name: string | null;
    actor_email: string | null;
};
type Filters = { action: string; actor: string; target_type: string; from: string; to: string };

export default function Index({
    logs,
    filters,
    actions,
    target_types,
}: {
    logs: { data: Row[]; links: PaginationLink[] };
    filters: Filters;
    actions: string[];
    target_types: string[];
}) {
    const [values, setValues] = useState<Filters>(filters);
    const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v !== "")).toString();

    return (
        <AppLayout title="سجل التدقيق">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="سجل التدقيق" description="سجل دائم لكل الإجراءات المهمة. لا يمكن تعديله أو حذفه." />
                <SectionCard title="تصفية">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get("/admin/audit-logs", Object.fromEntries(Object.entries(values).filter(([, v]) => v !== "")), { preserveState: true });
                        }}
                    >
                        <div className="row g-2">
                            <div className="col-12 col-lg-3">
                                <SelectInput id="action" label="الإجراء" value={values.action} placeholder="الكل" options={actions.map((a) => ({ value: a, label: a }))} onChange={(v) => setValues({ ...values, action: v })} />
                            </div>
                            <div className="col-12 col-lg-3">
                                <SelectInput id="target_type" label="نوع السجل" value={values.target_type} placeholder="الكل" options={target_types.map((t) => ({ value: t, label: t.split("\\").pop() ?? t }))} onChange={(v) => setValues({ ...values, target_type: v })} />
                            </div>
                            <div className="col-12 col-lg-2">
                                <TextInput id="actor" name="actor" label="المستخدم (اسم أو بريد)" value={values.actor} onChange={(v) => setValues({ ...values, actor: v })} />
                            </div>
                            <div className="col-6 col-lg-2">
                                <TextInput id="from" name="from" type="date" label="من" value={values.from} onChange={(v) => setValues({ ...values, from: v })} />
                            </div>
                            <div className="col-6 col-lg-2">
                                <TextInput id="to" name="to" type="date" label="إلى" value={values.to} onChange={(v) => setValues({ ...values, to: v })} />
                            </div>
                        </div>
                        <div className="d-flex flex-wrap gap-2">
                            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }}>
                                عرض النتائج
                            </button>
                            <a href={`/admin/audit-logs/export${query ? `?${query}` : ""}`} className="btn btn-outline-primary" style={{ minHeight: "48px" }}>
                                <i className="bi bi-filetype-csv me-2" aria-hidden="true" />
                                تنزيل CSV
                            </a>
                        </div>
                    </form>
                </SectionCard>

                {logs.data.length === 0 ? (
                    <EmptyState title="لا توجد سجلات مطابقة" />
                ) : (
                    <>
                        <div className="d-lg-none d-flex flex-column gap-2">
                            {logs.data.map((log) => (
                                <Link key={log.id} href={`/admin/audit-logs/${log.id}`} className="card p-3 text-decoration-none text-body">
                                    <span className="fw-semibold" dir="ltr">{log.action}</span>
                                    <span className="small text-body-secondary">
                                        {log.created_at} · {log.actor_name ?? "النظام"} · {log.target_type} #{log.target_id}
                                    </span>
                                </Link>
                            ))}
                        </div>
                        <div className="d-none d-lg-block table-responsive">
                            <table className="table align-middle">
                                <thead>
                                    <tr>
                                        <th>الوقت</th>
                                        <th>المستخدم</th>
                                        <th>الإجراء</th>
                                        <th>السجل</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <tr key={log.id}>
                                            <td dir="ltr">{log.created_at}</td>
                                            <td>{log.actor_name ?? "النظام"}</td>
                                            <td dir="ltr">{log.action}</td>
                                            <td dir="ltr">
                                                {log.target_type} #{log.target_id}
                                            </td>
                                            <td>
                                                <Link href={`/admin/audit-logs/${log.id}`} className="btn btn-outline-primary btn-sm" style={{ minHeight: "40px" }}>
                                                    التفاصيل
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={logs.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
