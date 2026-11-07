import { Link } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { useT } from "../../../Hooks/useT";

type Log = {
    id: number;
    created_at: string | null;
    action: string;
    target_type: string;
    target_id: string;
    actor_name: string | null;
    actor_email: string | null;
    context_rows: { label: string; value: string }[];
};

export default function Show({ log }: { log: Log }) {
    const t = useT();

    return (
        <AppLayout title={t("admin.audit.show_title", undefined, "تفاصيل السجل")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.audit.log_number", { id: log.id }, `سجل رقم ${log.id}`)}
                    description={t("admin.audit.read_only", undefined, "للقراءة فقط.")}
                />
                <div>
                    <Link href="/admin/audit-logs" className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                        {t("admin.audit.back", undefined, "العودة إلى سجل التدقيق")}
                    </Link>
                </div>
                <SectionCard title={t("transactions.section.details", undefined, "البيانات")}>
                    <dl className="row mb-0">
                        <dt className="col-sm-3">{t("admin.audit.column.time", undefined, "الوقت")}</dt>
                        <dd className="col-sm-9" dir="ltr">
                            {log.created_at}
                        </dd>
                        <dt className="col-sm-3">{t("admin.audit.column.actor", undefined, "المستخدم")}</dt>
                        <dd className="col-sm-9">
                            {log.actor_name ?? t("admin.audit.system", undefined, "النظام")}{" "}
                            {log.actor_email ? <span dir="ltr">({log.actor_email})</span> : null}
                        </dd>
                        <dt className="col-sm-3">{t("admin.audit.column.action", undefined, "الإجراء")}</dt>
                        <dd className="col-sm-9" dir="ltr">
                            {log.action}
                        </dd>
                        <dt className="col-sm-3">{t("admin.audit.affected_record", undefined, "السجل المتأثر")}</dt>
                        <dd className="col-sm-9" dir="ltr">
                            {log.target_type} #{log.target_id}
                        </dd>
                    </dl>
                </SectionCard>
                <SectionCard title={t("admin.audit.change_details", undefined, "تفاصيل التغيير (قبل / بعد)")}>
                    {log.context_rows.length ? (
                        <div className="table-responsive">
                            <table className="table align-middle mb-0">
                                <caption className="visually-hidden">{t("admin.audit.change_details", undefined, "تفاصيل التغيير")}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{t("admin.audit.field", undefined, "البيان")}</th>
                                        <th scope="col">{t("admin.audit.value", undefined, "القيمة")}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {log.context_rows.map((row, index) => (
                                        <tr key={index}>
                                            <th scope="row">{row.label}</th>
                                            <td style={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
                                                <bdi>{row.value}</bdi>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-body-secondary mb-0">{t("admin.audit.no_extra_details", undefined, "لا توجد تفاصيل إضافية لهذا الإجراء.")}</p>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}
