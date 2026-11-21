import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { PackageView } from "../../Components/Referrals/PackageView";
import type { ReferralPackage } from "../../types/referrals";
import { useT } from "../../Hooks/useT";

type Props = {
    referral: {
        public_id: string;
        institution: string | null;
        status: string;
        status_label: string;
        purpose_ar: string;
        disclosed_items: { key: string; label: string }[];
        info_request_note: string | null;
        decision_note: string | null;
        outcome_note: string | null;
        readiness_assessment_id?: number | null;
    };
    package: ReferralPackage;
    consent: {
        active: { version: string; granted_at: string } | null;
        history: { status: string; version: string; granted_at: string | null; withdrawn_at: string | null }[];
        text_ar: string;
        version: string;
        withdrawal_notice: string;
    };
    history: { from: string | null; to: string; note: string | null; actor: string | null; at: string | null }[];
    can: { consent: boolean; withdraw: boolean; share: boolean; update?: boolean; cancel?: boolean; mfi_transitions: string[]; record_outcome: boolean };
    edit?: { disclosableItems: Record<string, string>; assessments: { id: number; public_id: string; score: string | null; finalized_at: string | null }[] } | null;
    statusLabels: Record<string, string>;
};

function DraftEditForm({ referral, edit, url }: { referral: Props["referral"]; edit: NonNullable<Props["edit"]>; url: string }) {
    const t = useT();
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [purpose, setPurpose] = useState(referral.purpose_ar);
    const [items, setItems] = useState<string[]>(referral.disclosed_items.map((i) => i.key));
    const [assessmentId, setAssessmentId] = useState(referral.readiness_assessment_id ? String(referral.readiness_assessment_id) : "");
    const toggle = (key: string) => setItems((cur) => (cur.includes(key) ? cur.filter((k) => k !== key) : [...cur, key]));

    if (!open) {
        return (
            <button type="button" className="btn btn-outline-primary mt-3 me-2" style={{ minHeight: "48px" }} onClick={() => setOpen(true)}>
                {t("referrals.show.edit_draft", undefined, "تعديل مسودة الإحالة")}
            </button>
        );
    }

    return (
        <SectionCard title={t("referrals.show.edit_draft", undefined, "تعديل مسودة الإحالة")}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.put(
                        url,
                        { purpose_ar: purpose, disclosed_items: items, readiness_assessment_id: items.includes("readiness_assessment") && assessmentId ? Number(assessmentId) : null },
                        { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: () => setOpen(false) },
                    );
                }}
            >
                <div className="mb-3">
                    <label htmlFor="referral-purpose" className="form-label">{t("referrals.enterprise.purpose", undefined, "الغرض من الإحالة")}</label>
                    <textarea id="referral-purpose" className={`form-control${errors.purpose_ar ? " is-invalid" : ""}`} rows={3} value={purpose} onChange={(e) => setPurpose(e.target.value)} />
                    {errors.purpose_ar ? <div className="invalid-feedback d-block" role="alert">{errors.purpose_ar}</div> : null}
                </div>
                <fieldset className="mb-3">
                    <legend className="fs-6 fw-semibold">{t("referrals.enterprise.disclosed_items", undefined, "المعلومات التي ستُشارك")}</legend>
                    {Object.entries(edit.disclosableItems).map(([key, label]) => (
                        <div className="form-check" key={key}>
                            <input id={`edit-item-${key}`} type="checkbox" className="form-check-input" checked={items.includes(key)} onChange={() => toggle(key)} />
                            <label htmlFor={`edit-item-${key}`} className="form-check-label">{label}</label>
                        </div>
                    ))}
                    {errors.disclosed_items ? <div className="invalid-feedback d-block" role="alert">{errors.disclosed_items}</div> : null}
                </fieldset>
                {items.includes("readiness_assessment") ? (
                    <div className="mb-3">
                        <label htmlFor="referral-assessment" className="form-label">{t("readiness.assessments_section", undefined, "التقييم")}</label>
                        <select id="referral-assessment" className="form-select" style={{ minHeight: "48px" }} value={assessmentId} onChange={(e) => setAssessmentId(e.target.value)}>
                            <option value="">{t("referrals.enterprise.choose_assessment", undefined, "اختر تقييماً نهائياً")}</option>
                            {edit.assessments.map((a) => <option key={a.id} value={a.id}>{`${a.finalized_at ?? ""} - ${a.score ?? ""}`}</option>)}
                        </select>
                        {errors.readiness_assessment_id ? <div className="invalid-feedback d-block" role="alert">{errors.readiness_assessment_id}</div> : null}
                    </div>
                ) : null}
                <div className="d-flex flex-wrap gap-2">
                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={busy || !purpose.trim() || items.length === 0}>
                        {t("referrals.show.save_draft", undefined, "حفظ التعديلات")}
                    </button>
                    <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "48px" }} onClick={() => setOpen(false)}>{t("common.cancel", undefined, "إلغاء")}</button>
                </div>
            </form>
        </SectionCard>
    );
}

export default function Show({ referral, package: pkg, consent, history, can, edit = null, statusLabels }: Props) {
    const t = useT();
    const errors = usePage().props.errors as Record<string, string>;
    const [confirm, setConfirm] = useState(false);
    const [note, setNote] = useState("");
    const url = `/referrals/${referral.public_id}`;

    return (
        <AppLayout title={t("referrals.show.title", undefined, "الإحالة")}>
            <div className="app-page">
                <PageHeader
                    title={t("referrals.show.to_institution", { institution: referral.institution ?? "" }, `إحالة إلى ${referral.institution ?? ""}`)}
                    description={referral.purpose_ar}
                />
                <StatusBadge label={referral.status_label} tone="neutral" />

                <SectionCard title={t("referrals.enterprise.disclosed_items", undefined, "المعلومات التي ستُشارك")}>
                    <p className="mb-1">
                        {t("referrals.show.recipient", undefined, "المؤسسة المستلمة")}: <strong>{referral.institution}</strong>
                    </p>
                    <ul>
                        {referral.disclosed_items.map((i) => (
                            <li key={i.key}>{i.label}</li>
                        ))}
                    </ul>
                </SectionCard>
                <PackageView pkg={pkg} />

                <SectionCard title={t("referrals.show.consent", undefined, "الموافقة")}>
                    {consent.active ? (
                        <p>
                            {t("referrals.show.consent_granted", { version: consent.active.version, date: consent.active.granted_at }, `تمت الموافقة (نسخة ${consent.active.version}) بتاريخ ${consent.active.granted_at}`)}
                        </p>
                    ) : (
                        <p>{t("referrals.show.no_consent", undefined, "لا توجد موافقة فعّالة.")}</p>
                    )}
                    {can.consent ? (
                        <div>
                            <p>{consent.text_ar}</p>
                            <div className="form-check mb-2">
                                <input id="confirm" type="checkbox" className="form-check-input" checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />
                                <label htmlFor="confirm" className="form-check-label">
                                    {t("referrals.show.consent_confirm_label", undefined, "أوافق صراحةً على مشاركة المعلومات أعلاه مع هذه المؤسسة")}
                                </label>
                            </div>
                            {errors?.confirm ? <p className="text-danger">{errors.confirm}</p> : null}
                            <button
                                type="button"
                                className="btn btn-primary"
                                style={{ minHeight: "48px" }}
                                disabled={!confirm}
                                onClick={() => router.post(`${url}/consent`, { confirm: true })}
                            >
                                {t("referrals.show.confirm_consent", undefined, "تأكيد الموافقة")}
                            </button>
                        </div>
                    ) : null}
                    {can.withdraw ? (
                        <div className="mt-2">
                            <p className="text-muted small">{consent.withdrawal_notice}</p>
                            <button
                                type="button"
                                className="btn btn-outline-danger"
                                style={{ minHeight: "48px" }}
                                onClick={() => router.post(`${url}/consent/withdraw`)}
                            >
                                {t("referrals.show.withdraw_consent", undefined, "سحب الموافقة")}
                            </button>
                        </div>
                    ) : null}
                </SectionCard>

                {can.share ? (
                    <button type="button" className="btn btn-primary mt-3" style={{ minHeight: "48px" }} onClick={() => router.post(`${url}/share`)}>
                        {t("referrals.show.share", undefined, "مشاركة الإحالة مع المؤسسة")}
                    </button>
                ) : null}
                {errors?.consent ? <p className="text-danger">{errors.consent}</p> : null}

                {can.update && edit ? <DraftEditForm referral={referral} edit={edit} url={url} /> : null}
                {can.cancel ? (
                    <button
                        type="button"
                        className="btn btn-outline-danger mt-3"
                        style={{ minHeight: "48px" }}
                        onClick={() => {
                            if (window.confirm(t("referrals.show.cancel_confirm", undefined, "هل تريد إلغاء مسودة الإحالة هذه؟ لن تُشارك مع المؤسسة، وأي موافقة قائمة ستُسحب."))) {
                                router.post(`${url}/cancel`);
                            }
                        }}
                    >
                        {t("referrals.show.cancel_referral", undefined, "إلغاء الإحالة")}
                    </button>
                ) : null}

                {referral.info_request_note || referral.decision_note || referral.outcome_note ? (
                    <SectionCard title={t("common.notes", undefined, "الملاحظات")}>
                        {referral.info_request_note ? (
                            <div className="app-record-line">
                                <span>{t("referrals.show.info_requested", undefined, "معلومات مطلوبة")}</span>
                                <span>{referral.info_request_note}</span>
                            </div>
                        ) : null}
                        {referral.decision_note ? (
                            <div className="app-record-line">
                                <span>{t("referrals.show.decision", undefined, "القرار")}</span>
                                <span>{referral.decision_note}</span>
                            </div>
                        ) : null}
                        {referral.outcome_note ? (
                            <div className="app-record-line">
                                <span>{t("referrals.show.outcome", undefined, "نتيجة التمويل")}</span>
                                <span>{referral.outcome_note}</span>
                            </div>
                        ) : null}
                    </SectionCard>
                ) : null}

                {can.mfi_transitions.length || can.record_outcome ? (
                    <SectionCard title={t("referrals.show.update", undefined, "تحديث الإحالة")}>
                        <textarea
                            aria-label={t("common.notes", undefined, "ملاحظة")}
                            className="form-control mb-2"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                        <div className="d-flex flex-wrap gap-2">
                            {can.mfi_transitions.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    className="btn btn-outline-primary"
                                    style={{ minHeight: "44px" }}
                                    onClick={() => router.post(`${url}/status`, { status: s, note })}
                                >
                                    {statusLabels[s]}
                                </button>
                            ))}
                            {can.record_outcome ? (
                                <button
                                    type="button"
                                    className="btn btn-primary"
                                    style={{ minHeight: "44px" }}
                                    onClick={() => router.post(`${url}/outcome`, { outcome_note: note })}
                                >
                                    {t("referrals.show.record_outcome", undefined, "تسجيل نتيجة التمويل")}
                                </button>
                            ) : null}
                        </div>
                    </SectionCard>
                ) : null}

                {history.length ? (
                    <SectionCard title={t("referrals.show.status_history", undefined, "سجل الحالات")}>
                        <ul className="mb-0">
                            {history.map((h, i) => (
                                <li key={i}>
                                    {h.at} - {statusLabels[h.to] ?? h.to}
                                    {h.actor ? ` - ${h.actor}` : ""}
                                    {h.note ? `: ${h.note}` : ""}
                                </li>
                            ))}
                        </ul>
                    </SectionCard>
                ) : null}
            </div>
        </AppLayout>
    );
}
