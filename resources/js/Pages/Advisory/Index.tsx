import { router, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { useT } from "../../Hooks/useT";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { StatusBadge, type StatusTone } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";

type RecordStatus = "open" | "completed";

type ActivityRow = {
    id: number;
    activity_date: string | null;
    notes: string | null;
    target_date: string | null;
    status: RecordStatus;
    status_label: string;
    responsible_name: string | null;
};

type FollowUpRow = {
    id: number;
    description: string;
    target_date: string | null;
    status: RecordStatus;
    status_label: string;
    responsible_name: string | null;
    can_complete: boolean;
};

type AdvisoryPageProps = {
    enterprise: {
        public_id: string;
        business_name: string;
    };
    canRecord: boolean;
    activities: ActivityRow[];
    followUps: FollowUpRow[];
};

function statusTone(status: RecordStatus): StatusTone {
    return status === "completed" ? "success" : "warning";
}

function RecordStatusBadge({ status, label }: { status: RecordStatus; label: string }) {
    return <StatusBadge label={label} tone={statusTone(status)} />;
}

export default function Index({ enterprise, canRecord, activities, followUps }: AdvisoryPageProps) {
    const t = useT();
    const form = useForm({
        activity_date: "",
        notes: "",
        follow_up_description: "",
        target_date: "",
    });

    const submit = () => {
        form.post(`/enterprises/${enterprise.public_id}/advisory`, {
            preserveScroll: true,
        });
    };

    const completeFollowUp = (followUpId: number) => {
        router.post(`/follow-ups/${followUpId}/complete`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout title={t("advisory.title", undefined, "الإرشاد")}>
            <div className="app-page">
                <PageHeader
                    title={t("advisory.visits_title", undefined, "الزيارات الإرشادية")}
                    description={t("advisory.visits_description", { name: enterprise.business_name }, `${enterprise.business_name} — سجل النشاط والمتابعة.`)}
                />

                {canRecord ? (
                    <SectionCard title={t("advisory.record_activity", undefined, "تسجيل نشاط")}>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                submit();
                            }}
                        >
                            <TextInput
                                id="activity_date"
                                label={t("advisory.activity_date", undefined, "تاريخ النشاط")}
                                name="activity_date"
                                type="date"
                                value={form.data.activity_date}
                                onChange={(value) => form.setData("activity_date", value)}
                                error={form.errors.activity_date}
                            />
                            <div className="mb-3">
                                <label htmlFor="notes" className="form-label mb-1 fw-semibold">
                                    {t("common.notes", undefined, "ملاحظات")}
                                </label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    className="form-control"
                                    style={{ minHeight: "96px", fontSize: "16px" }}
                                    value={form.data.notes}
                                    onChange={(event) => form.setData("notes", event.target.value)}
                                />
                            </div>
                            <TextInput
                                id="follow_up_description"
                                label={t("advisory.follow_up_optional", undefined, "إجراء متابعة (اختياري)")}
                                name="follow_up_description"
                                value={form.data.follow_up_description}
                                onChange={(value) => form.setData("follow_up_description", value)}
                                error={form.errors.follow_up_description}
                                help={t("advisory.follow_up_help", undefined, "اتركه فارغاً إذا لم يكن هناك إجراء لاحق.")}
                            />
                            <TextInput
                                id="target_date"
                                label={t("advisory.target_date_optional", undefined, "التاريخ المستهدف (اختياري)")}
                                name="target_date"
                                type="date"
                                value={form.data.target_date}
                                onChange={(value) => form.setData("target_date", value)}
                                error={form.errors.target_date}
                            />
                            <button
                                type="submit"
                                className="btn btn-primary"
                                style={{ minHeight: "48px" }}
                                disabled={form.processing}
                            >
                                {t("advisory.save_activity", undefined, "حفظ النشاط")}
                            </button>
                        </form>
                    </SectionCard>
                ) : null}

                <div className="mt-4">
                    <h2 className="h5 mb-3">{t("advisory.activities", undefined, "الأنشطة")}</h2>
                    {activities.length === 0 ? (
                        <EmptyState title={t("advisory.empty_activities", undefined, "لا توجد زيارات إرشادية بعد")} />
                    ) : (
                        <>
                            <div className="d-lg-none d-flex flex-column gap-3">
                                {activities.map((activity) => (
                                    <article key={activity.id} className="border rounded p-3 bg-white">
                                        <div className="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <strong>{activity.activity_date}</strong>
                                            <RecordStatusBadge status={activity.status} label={activity.status_label} />
                                        </div>
                                        {activity.notes ? <p className="mb-2">{activity.notes}</p> : null}
                                        {activity.responsible_name ? (
                                            <p className="mb-1">{t("advisory.responsible", undefined, "المسؤول")}: {activity.responsible_name}</p>
                                        ) : null}
                                        {activity.target_date ? <p className="mb-0">{t("advisory.target_date", undefined, "التاريخ المستهدف")}: {activity.target_date}</p> : null}
                                    </article>
                                ))}
                            </div>
                            <div className="d-none d-lg-block table-responsive">
                                <table className="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>{t("common.date", undefined, "التاريخ")}</th>
                                            <th>{t("common.notes", undefined, "ملاحظات")}</th>
                                            <th>{t("advisory.responsible", undefined, "المسؤول")}</th>
                                            <th>{t("advisory.target_date", undefined, "التاريخ المستهدف")}</th>
                                            <th>{t("common.status", undefined, "الحالة")}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activities.map((activity) => (
                                            <tr key={activity.id}>
                                                <td>{activity.activity_date}</td>
                                                <td>{activity.notes}</td>
                                                <td>{activity.responsible_name}</td>
                                                <td>{activity.target_date}</td>
                                                <td>
                                                    <RecordStatusBadge status={activity.status} label={activity.status_label} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>

                <div className="mt-4">
                    <h2 className="h5 mb-3">{t("advisory.follow_ups", undefined, "إجراءات المتابعة")}</h2>
                    {followUps.length === 0 ? (
                        <EmptyState title={t("advisory.empty_follow_ups", undefined, "لا توجد إجراءات متابعة بعد")} />
                    ) : (
                        <>
                            <div className="d-lg-none d-flex flex-column gap-3">
                                {followUps.map((followUp) => (
                                    <article key={followUp.id} className="border rounded p-3 bg-white">
                                        <div className="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <strong>{followUp.description}</strong>
                                            <RecordStatusBadge status={followUp.status} label={followUp.status_label} />
                                        </div>
                                        {followUp.responsible_name ? (
                                            <p className="mb-1">المسؤول: {followUp.responsible_name}</p>
                                        ) : null}
                                        {followUp.target_date ? (
                                            <p className="mb-2">{t("advisory.target_date", undefined, "التاريخ المستهدف")}: {followUp.target_date}</p>
                                        ) : null}
                                        {followUp.can_complete && followUp.status === "open" ? (
                                            <button
                                                type="button"
                                                className="btn btn-outline-primary"
                                                style={{ minHeight: "48px" }}
                                                onClick={() => completeFollowUp(followUp.id)}
                                            >
                                                {t("advisory.confirm_follow_up", undefined, "تأكيد إنجاز الإجراء")}
                                            </button>
                                        ) : null}
                                    </article>
                                ))}
                            </div>
                            <div className="d-none d-lg-block table-responsive">
                                <table className="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>{t("advisory.action", undefined, "الإجراء")}</th>
                                            <th>{t("advisory.responsible", undefined, "المسؤول")}</th>
                                            <th>{t("advisory.target_date", undefined, "التاريخ المستهدف")}</th>
                                            <th>{t("common.status", undefined, "الحالة")}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {followUps.map((followUp) => (
                                            <tr key={followUp.id}>
                                                <td>{followUp.description}</td>
                                                <td>{followUp.responsible_name}</td>
                                                <td>{followUp.target_date}</td>
                                                <td>
                                                    <RecordStatusBadge status={followUp.status} label={followUp.status_label} />
                                                </td>
                                                <td>
                                                    {followUp.can_complete && followUp.status === "open" ? (
                                                        <button
                                                            type="button"
                                                            className="btn btn-outline-primary"
                                                            style={{ minHeight: "44px" }}
                                                            onClick={() => completeFollowUp(followUp.id)}
                                                        >
                                                            {t("advisory.confirm_follow_up", undefined, "تأكيد إنجاز الإجراء")}
                                                        </button>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
