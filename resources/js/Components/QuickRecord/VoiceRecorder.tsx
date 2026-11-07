import { router } from "@inertiajs/react";
import { useState } from "react";
import { useT } from "../../Hooks/useT";
import { ReviewForm as TransactionDraftReview, initialReviewData, withVoiceDefaultDate, reviewPayload, type ReviewFormData } from "../Transactions/ReviewForm";
import { BrowserSpeechCapture } from "./BrowserSpeechCapture";
import { getSpeechRecognition, type SpeechRecognitionCtor } from "./browserSpeech";
import { sendTranscript, type VoiceDraftResponse, type VoiceResult } from "./voiceApi";

type Phase = { name: "record" } | { name: "sending" } | { name: "review"; res: VoiceDraftResponse; data: ReviewFormData } | { name: "error"; message: string; unavailable: boolean };

/**
 * Voice draft via browser speech recognition only (always on; no user toggle).
 * Transcript → server draft → same review form as receipts → confirm to response.confirm.url.
 */
export function VoiceRecorder({ voiceUrl, manualUrl, onDone, sendText = sendTranscript, speechCtor = getSpeechRecognition() }: {
    voiceUrl: string; manualUrl: string; onDone?: () => void; sendText?: typeof sendTranscript;
    /** Web Speech API constructor; null (e.g. Firefox) shows unsupported + manual entry. */
    speechCtor?: SpeechRecognitionCtor | null;
}) {
    const t = useT();
    const [phase, setPhase] = useState<Phase>({ name: "record" });
    const [errors, setErrors] = useState<Partial<Record<keyof ReviewFormData, string>>>({});
    const [saving, setSaving] = useState(false);

    const handle = (r: VoiceResult) => {
        if (!r.ok) { setPhase({ name: "error", message: r.message, unavailable: r.kind === "unavailable" }); return; }
        const d = r.data;
        const res = { ...d, draft: withVoiceDefaultDate({ source: "voice", ...d.draft }) };
        setPhase({ name: "review", res, data: initialReviewData(res.draft, d.type, d.options, d.category_suggestion, d.transaction_type_options) });
    };

    const submitText = async (text: string) => {
        setPhase({ name: "sending" });
        handle(await sendText(voiceUrl, text));
    };

    if (phase.name === "review") {
        const { res, data } = phase;
        return (
            <div>
                {res.message ? <p className="small text-body-secondary">{res.message}</p> : null}
                {res.draft.raw_text ? (
                    <blockquote className="app-mobile-record small mb-3">
                        <span className="text-body-secondary d-block mb-1">{t("voice.understood_from_recording", undefined, "ما فهمناه من تسجيلك:")}</span>
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
                    sourceLabel={t("voice.source_label", undefined, "التسجيل الصوتي")}
                    onSubmit={() => {
                        setSaving(true);
                        router.post(res.confirm.url, reviewPayload(data), {
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
                    <button type="button" className="btn btn-primary" style={{ minHeight: 48 }} onClick={() => setPhase({ name: "record" })}>{t("voice.try_again", undefined, "حاول مرة أخرى")}</button>
                    <a href={manualUrl} className="btn btn-outline-primary" style={{ minHeight: 48, display: "inline-flex", alignItems: "center" }}>{t("quick_record.manual", undefined, "إدخال يدوي")}</a>
                </div>
            </div>
        );
    }

    if (!speechCtor) {
        return (
            <div className="d-flex flex-column gap-2 text-center py-2">
                <div className="alert alert-warning mb-0" role="alert">
                    {t("voice.unsupported_browser", undefined, "متصفحك لا يدعم التعرّف على الكلام. استخدم Chrome أو Edge، أو أدخل العملية يدوياً.")}
                </div>
                <a href={manualUrl} className="btn btn-outline-primary" style={{ minHeight: 48, display: "inline-flex", alignItems: "center", justifyContent: "center" }}>{t("quick_record.manual", undefined, "إدخال يدوي")}</a>
            </div>
        );
    }

    return (
        <div className="d-flex flex-column align-items-center gap-3 text-center py-2">
            <p className="mb-0 text-body-secondary">{t("voice.example_phrase", undefined, "قل مثلاً: «دفعت خمسين ألف ليرة إيجار المحل اليوم».")}</p>
            <BrowserSpeechCapture Ctor={speechCtor} busy={phase.name === "sending"} onSubmit={(tx) => void submitText(tx)} />
            {phase.name === "sending" ? <div aria-live="polite" className="small">{t("voice.converting_to_draft", undefined, "جارٍ تحويل النص إلى مسودة…")}</div> : null}
        </div>
    );
}
