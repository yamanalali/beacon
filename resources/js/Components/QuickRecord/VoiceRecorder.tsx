import { router } from "@inertiajs/react";
import { useState } from "react";
import { ReviewForm as TransactionDraftReview, initialReviewData, type ReviewFormData } from "../Transactions/ReviewForm";
import { MAX_SECONDS, useWavRecorder } from "./useWavRecorder";
import { sendVoice, type VoiceDraftResponse } from "./voiceApi";

type Phase = { name: "record" } | { name: "sending" } | { name: "review"; res: VoiceDraftResponse; data: ReviewFormData } | { name: "error"; message: string; unavailable: boolean };

/** Record → server draft → same review form as receipts → confirm to response.confirm.url. Reject = discard. */
export function VoiceRecorder({ voiceUrl, manualUrl, onDone, send = sendVoice }: {
    voiceUrl: string; manualUrl: string; onDone?: () => void; send?: typeof sendVoice;
}) {
    const rec = useWavRecorder();
    const [phase, setPhase] = useState<Phase>({ name: "record" });
    const [errors, setErrors] = useState<Partial<Record<keyof ReviewFormData, string>>>({});
    const [saving, setSaving] = useState(false);

    const finish = async () => {
        const blob = await rec.stop();
        if (!blob) { setPhase({ name: "error", message: "لم يُسجَّل أي صوت. حاول مرة أخرى.", unavailable: false }); return; }
        setPhase({ name: "sending" });
        const r = await send(voiceUrl, blob);
        if (!r.ok) { setPhase({ name: "error", message: r.message, unavailable: r.kind === "unavailable" }); return; }
        const d = r.data;
        setPhase({ name: "review", res: d, data: initialReviewData(d.draft, d.type, d.options, d.category_suggestion, d.transaction_type_options) });
    };

    if (phase.name === "review") {
        const { res, data } = phase;
        return (
            <div>
                {res.message ? <p className="small text-body-secondary">{res.message}</p> : null}
                {res.draft.raw_text ? (
                    <blockquote className="app-mobile-record small mb-3">
                        <span className="text-body-secondary d-block mb-1">ما فهمناه من تسجيلك:</span>
                        {res.draft.raw_text}
                    </blockquote>
                ) : null}
                <TransactionDraftReview
                    draft={res.draft}
                    data={data}
                    setData={(p) => setPhase({ ...phase, data: { ...data, ...p } })}
                    options={res.options}
                    suggestion={res.category_suggestion}
                    typeOptions={res.transaction_type_options}
                    errors={errors}
                    processing={saving}
                    sourceLabel="التسجيل الصوتي"
                    onSubmit={() => {
                        setSaving(true);
                        router.post(res.confirm.url, data, {
                            onError: (e) => setErrors(e as typeof errors),
                            onSuccess: () => onDone?.(),
                            onFinish: () => setSaving(false),
                        });
                    }}
                    onReject={() => { setErrors({}); setPhase({ name: "record" }); }}
                />
            </div>
        );
    }

    if (phase.name === "error") {
        return (
            <div className="d-flex flex-column gap-2">
                <div className={`alert ${phase.unavailable ? "alert-info" : "alert-danger"} mb-0`} role="alert">{phase.message}</div>
                <div className="d-flex flex-wrap gap-2">
                    {!phase.unavailable ? <button type="button" className="btn btn-primary" style={{ minHeight: 48 }} onClick={() => setPhase({ name: "record" })}>حاول مرة أخرى</button> : null}
                    <a href={manualUrl} className="btn btn-outline-primary" style={{ minHeight: 48, display: "inline-flex", alignItems: "center" }}>إدخال يدوي</a>
                </div>
            </div>
        );
    }

    const recording = rec.state === "recording";
    return (
        <div className="d-flex flex-column align-items-center gap-3 text-center py-2">
            <p className="mb-0 text-body-secondary">قل مثلاً: «دفعت خمسين ألف ليرة إيجار المحل اليوم».</p>
            {rec.state === "denied" ? <div className="alert alert-warning mb-0" role="alert">لم يُسمح باستخدام الميكروفون. فعّله من إعدادات المتصفح ثم حاول مرة أخرى.</div> : null}
            {rec.state === "unsupported" ? <div className="alert alert-warning mb-0" role="alert">متصفحك لا يدعم التسجيل الصوتي. استخدم الإدخال اليدوي.</div> : null}
            <button type="button" className={`btn ${recording ? "btn-danger" : "btn-primary"} rounded-circle`} style={{ width: 88, height: 88, fontSize: 32 }}
                aria-pressed={recording} aria-label={recording ? "إيقاف التسجيل وإرساله" : "ابدأ التسجيل"}
                disabled={phase.name === "sending"} onClick={() => (recording ? void finish() : void rec.start())}>
                <i className={`bi ${recording ? "bi-stop-fill" : "bi-mic-fill"}`} aria-hidden="true" />
            </button>
            <div aria-live="polite" className="small">
                {phase.name === "sending" ? "جارٍ تحويل التسجيل إلى مسودة…" : recording ? <>جارٍ التسجيل… <bdi dir="ltr">{rec.seconds}s / {MAX_SECONDS}s</bdi></> : "اضغط للتسجيل، واضغط مرة أخرى للإيقاف."}
            </div>
            {recording ? <button type="button" className="btn btn-link" onClick={rec.cancel}>إلغاء</button> : null}
        </div>
    );
}
