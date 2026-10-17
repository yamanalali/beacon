import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { PackageView } from "../../Components/Referrals/PackageView";
import type { ReferralPackage } from "../../types/referrals";

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
    can: { consent: boolean; withdraw: boolean; share: boolean; mfi_transitions: string[]; record_outcome: boolean };
    statusLabels: Record<string, string>;
};

export default function Show({ referral, package: pkg, consent, history, can, statusLabels }: Props) {
    const errors = usePage().props.errors as Record<string, string>;
    const [confirm, setConfirm] = useState(false);
    const [note, setNote] = useState("");
    const url = `/referrals/${referral.public_id}`;

    return (
        <AppLayout title="الإحالة">
            <div className="app-page">
                <PageHeader title={`إحالة إلى ${referral.institution ?? ""}`} description={referral.purpose_ar} />
                <StatusBadge label={referral.status_label} tone="neutral" />

                <SectionCard title="المعلومات التي ستُشارك">
                    <p className="mb-1">المؤسسة المستلمة: <strong>{referral.institution}</strong></p>
                    <ul>{referral.disclosed_items.map((i) => <li key={i.key}>{i.label}</li>)}</ul>
                </SectionCard>
                <PackageView pkg={pkg} />

                <SectionCard title="الموافقة">
                    {consent.active ? <p>تمت الموافقة (نسخة {consent.active.version}) بتاريخ {consent.active.granted_at}</p> : <p>لا توجد موافقة فعّالة.</p>}
                    {can.consent ? (
                        <div>
                            <p>{consent.text_ar}</p>
                            <div className="form-check mb-2">
                                <input id="confirm" type="checkbox" className="form-check-input" checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />
                                <label htmlFor="confirm" className="form-check-label">أوافق صراحةً على مشاركة المعلومات أعلاه مع هذه المؤسسة</label>
                            </div>
                            {errors?.confirm ? <p className="text-danger">{errors.confirm}</p> : null}
                            <button type="button" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={!confirm} onClick={() => router.post(`${url}/consent`, { confirm: true })}>تأكيد الموافقة</button>
                        </div>
                    ) : null}
                    {can.withdraw ? (
                        <div className="mt-2">
                            <p className="text-muted small">{consent.withdrawal_notice}</p>
                            <button type="button" className="btn btn-outline-danger" style={{ minHeight: "48px" }} onClick={() => router.post(`${url}/consent/withdraw`)}>سحب الموافقة</button>
                        </div>
                    ) : null}
                </SectionCard>

                {can.share ? <button type="button" className="btn btn-primary mt-3" style={{ minHeight: "48px" }} onClick={() => router.post(`${url}/share`)}>مشاركة الإحالة مع المؤسسة</button> : null}
                {errors?.consent ? <p className="text-danger">{errors.consent}</p> : null}

                {referral.info_request_note || referral.decision_note || referral.outcome_note ? (
                    <SectionCard title="الملاحظات">
                        {referral.info_request_note ? <div className="app-record-line"><span>معلومات مطلوبة</span><span>{referral.info_request_note}</span></div> : null}
                        {referral.decision_note ? <div className="app-record-line"><span>القرار</span><span>{referral.decision_note}</span></div> : null}
                        {referral.outcome_note ? <div className="app-record-line"><span>نتيجة التمويل</span><span>{referral.outcome_note}</span></div> : null}
                    </SectionCard>
                ) : null}

                {can.mfi_transitions.length || can.record_outcome ? (
                    <SectionCard title="تحديث الإحالة">
                        <textarea aria-label="ملاحظة" className="form-control mb-2" value={note} onChange={(e) => setNote(e.target.value)} />
                        <div className="d-flex flex-wrap gap-2">
                            {can.mfi_transitions.map((s) => (
                                <button key={s} type="button" className="btn btn-outline-primary" style={{ minHeight: "44px" }} onClick={() => router.post(`${url}/status`, { status: s, note })}>{statusLabels[s]}</button>
                            ))}
                            {can.record_outcome ? <button type="button" className="btn btn-primary" style={{ minHeight: "44px" }} onClick={() => router.post(`${url}/outcome`, { outcome_note: note })}>تسجيل نتيجة التمويل</button> : null}
                        </div>
                    </SectionCard>
                ) : null}

                {history.length ? (
                    <SectionCard title="سجل الحالات">
                        <ul className="mb-0">{history.map((h, i) => <li key={i}>{h.at} - {statusLabels[h.to] ?? h.to}{h.actor ? ` - ${h.actor}` : ""}{h.note ? `: ${h.note}` : ""}</li>)}</ul>
                    </SectionCard>
                ) : null}
            </div>
        </AppLayout>
    );
}
