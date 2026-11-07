import { router } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type Session = { is_current: boolean; ip_address: string | null; user_agent: string; last_activity: string };

export default function Sessions({ sessions, supported }: { sessions: Session[]; supported: boolean }) {
    const t = useT();

    return (
        <AppLayout title={t("operations.sessions.title", undefined, "الجلسات")}>
            <div className="app-page">
                <PageHeader
                    title={t("operations.sessions.page_title", undefined, "جلسات الدخول")}
                    description={t("operations.sessions.page_description", undefined, "الأجهزة التي سجّلت الدخول بحسابك.")}
                />
                {!supported ? <p>{t("operations.sessions.unsupported", undefined, "إدارة الجلسات غير متاحة في هذه البيئة.")}</p> : null}
                <div className="d-flex flex-column gap-2 mb-3">
                    {sessions.map((s, i) => (
                        <article key={i} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <span dir="ltr">{s.ip_address}</span>
                                {s.is_current ? (
                                    <StatusBadge label={t("operations.sessions.this_device", undefined, "هذا الجهاز")} tone="success" />
                                ) : null}
                            </div>
                            <small className="d-block text-muted" dir="ltr">
                                {s.user_agent}
                            </small>
                            <small className="d-block">
                                {t("operations.sessions.last_activity", undefined, "آخر نشاط")}: {s.last_activity}
                            </small>
                        </article>
                    ))}
                </div>
                {supported ? (
                    <button
                        type="button"
                        className="btn btn-outline-danger"
                        style={{ minHeight: "48px" }}
                        onClick={() => router.delete("/account/sessions/others")}
                    >
                        {t("operations.sessions.revoke_others", undefined, "إنهاء جميع الجلسات الأخرى")}
                    </button>
                ) : null}
            </div>
        </AppLayout>
    );
}
