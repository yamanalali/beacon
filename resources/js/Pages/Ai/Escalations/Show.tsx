import { Link, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../Layouts/AppLayout";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EscalationStatus, shortDate } from "../../../Components/Ai/aiShared";
import { useT } from "../../../Hooks/useT";

type Escalation = {
    id: string;
    question: string;
    status: string;
    created_at: string | null;
    enterprise_id?: string | null;
    staff_reply?: string | null;
    replied_at?: string | null;
    can_reply?: boolean;
};

type Props = { escalation: Escalation };

export default function Show({ escalation }: Props) {
    const t = useT();
    const flash = (usePage().props as { flash?: { success?: string } }).flash;
    const form = useForm({ staff_reply: "" });
    const canReply = escalation.can_reply === true;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/ai/pages/escalations/${escalation.id}/reply`, { preserveScroll: true });
    };

    return (
        <AppLayout title={t("ai.escalations.show_title", undefined, "تفاصيل الاستفسار")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("ai.escalations.show_title", undefined, "تفاصيل الاستفسار")}
                    description={
                        canReply || escalation.staff_reply
                            ? t("ai.escalations.desc_staff", undefined, "سؤال وصل إلى فريق بيكون.")
                            : t("ai.escalations.desc_user", undefined, "سؤال أرسلته إلى فريق بيكون.")
                    }
                />
                {flash?.success ? (
                    <div className="alert alert-success mb-0" role="status">
                        {flash.success}
                    </div>
                ) : null}
                <div>
                    <Link href="/ai/pages/escalations" className="btn btn-link px-0">
                        <i className="bi bi-arrow-right me-1" aria-hidden="true" />
                        {t("ai.escalations.back", undefined, "العودة إلى الاستفسارات")}
                    </Link>
                </div>

                <div className="row g-3 align-items-start">
                    <div className="col-12 col-lg-8">
                        <SectionCard title={t("ai.escalations.question_section", undefined, "السؤال")}>
                            <p className="mb-0" style={{ whiteSpace: "pre-line", fontSize: "1.05rem" }}>
                                {escalation.question}
                            </p>
                        </SectionCard>

                        {escalation.staff_reply ? (
                            <div className="mt-3">
                                <SectionCard title={t("ai.escalations.staff_reply", undefined, "رد فريق بيكون")}>
                                    <p className="mb-2" style={{ whiteSpace: "pre-line" }}>
                                        {escalation.staff_reply}
                                    </p>
                                    {escalation.replied_at ? (
                                        <p className="small text-body-secondary mb-0">
                                            {t("ai.escalations.replied_at", undefined, "تاريخ الرد")}:{" "}
                                            <bdi dir="ltr">{shortDate(escalation.replied_at)}</bdi>
                                        </p>
                                    ) : null}
                                </SectionCard>
                            </div>
                        ) : null}

                        {canReply ? (
                            <div className="mt-3">
                                <SectionCard title={t("ai.escalations.send_reply", undefined, "إرسال الرد")}>
                                    <form onSubmit={submit} className="d-flex flex-column gap-3">
                                        <div>
                                            <label htmlFor="staff_reply" className="form-label">
                                                {t("ai.escalations.reply_text", undefined, "نص الرد")}
                                            </label>
                                            <textarea
                                                id="staff_reply"
                                                name="staff_reply"
                                                rows={5}
                                                className={`form-control${form.errors.staff_reply ? " is-invalid" : ""}`}
                                                value={form.data.staff_reply}
                                                onChange={(e) => form.setData("staff_reply", e.target.value)}
                                                aria-invalid={form.errors.staff_reply ? true : undefined}
                                                aria-describedby={form.errors.staff_reply ? "staff_reply-error" : undefined}
                                                required
                                                minLength={3}
                                                maxLength={5000}
                                                disabled={form.processing}
                                            />
                                            {form.errors.staff_reply ? (
                                                <div id="staff_reply-error" className="invalid-feedback d-block" role="alert">
                                                    {form.errors.staff_reply}
                                                </div>
                                            ) : (
                                                <div className="form-text">
                                                    {t(
                                                        "ai.escalations.reply_help",
                                                        undefined,
                                                        "سيظهر الرد لصاحب السؤال وتُحدَّث حالة الاستفسار إلى «تم الرد».",
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        <div>
                                            <button type="submit" className="btn btn-primary" disabled={form.processing}>
                                                {form.processing ? t("common.loading", undefined, "جارٍ الحفظ…") : t("ai.escalations.save_reply", undefined, "حفظ الرد")}
                                            </button>
                                        </div>
                                    </form>
                                </SectionCard>
                            </div>
                        ) : null}
                    </div>
                    <div className="col-12 col-lg-4 d-flex flex-column gap-3">
                        <SectionCard title={t("ai.escalations.follow_up", undefined, "المتابعة")}>
                            <dl className="row mb-0 small">
                                <dt className="col-5 text-body-secondary fw-normal">{t("common.status", undefined, "الحالة")}</dt>
                                <dd className="col-7">
                                    <EscalationStatus status={escalation.status} />
                                </dd>
                                <dt className="col-5 text-body-secondary fw-normal">{t("ai.escalations.sent_at", undefined, "تاريخ الإرسال")}</dt>
                                <dd className="col-7">
                                    <bdi dir="ltr">{shortDate(escalation.created_at)}</bdi>
                                </dd>
                                {escalation.enterprise_id ? (
                                    <>
                                        <dt className="col-5 text-body-secondary fw-normal">{t("referrals.column.enterprise", undefined, "المنشأة")}</dt>
                                        <dd className="col-7 mb-0">
                                            <Link href={`/enterprises/${escalation.enterprise_id}`}>
                                                {t("smes.view_enterprise", undefined, "عرض المنشأة")}
                                            </Link>
                                        </dd>
                                    </>
                                ) : null}
                            </dl>
                        </SectionCard>
                        {!canReply && !escalation.staff_reply ? (
                            <SectionCard title={t("ai.escalations.reply", undefined, "الرد")}>
                                <p className="small text-body-secondary mb-0">
                                    {t(
                                        "ai.escalations.awaiting_reply",
                                        undefined,
                                        "سيظهر رد فريق بيكون هنا عند توفره، ويمكنك متابعة الحالة من قائمة الاستفسارات.",
                                    )}
                                </p>
                            </SectionCard>
                        ) : null}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
