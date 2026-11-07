import { useT } from "../../Hooks/useT";
import { useBrowserSpeech, type SpeechRecognitionCtor } from "./browserSpeech";
import { VOICE_TRANSCRIPT_MAX } from "./voiceApi";

/** Live transcript in an editable textarea; submit sends the text into the voice-draft flow. */
export function BrowserSpeechCapture({ Ctor, onSubmit, busy }: { Ctor: SpeechRecognitionCtor; onSubmit: (text: string) => void; busy?: boolean }) {
    const t = useT();
    const sp = useBrowserSpeech(Ctor);
    const listening = sp.state === "listening";
    const value = sp.text.slice(0, VOICE_TRANSCRIPT_MAX);
    const text = value.trim();
    const near = value.length >= VOICE_TRANSCRIPT_MAX * 0.9;
    return (
        <div className="d-flex flex-column align-items-center gap-3 w-100" data-testid="browser-speech">
            {sp.state === "denied" ? <div className="alert alert-warning mb-0 w-100" role="alert">{t("voice.mic_denied", undefined, "لم يُسمح باستخدام الميكروفون. فعّله من إعدادات المتصفح ثم حاول مرة أخرى.")}</div> : null}
            {sp.state === "error" ? <div className="alert alert-warning mb-0 w-100" role="alert">{t("voice.speech_error", undefined, "تعذّر التعرّف على الكلام. حاول مرة أخرى أو استخدم الإدخال اليدوي.")}</div> : null}
            <button type="button" className={`btn ${listening ? "btn-danger" : "btn-primary"} rounded-circle`} style={{ width: 88, height: 88, fontSize: 32 }}
                aria-pressed={listening} aria-label={listening ? t("voice.stop_listening", undefined, "إيقاف الاستماع") : t("voice.start_listening", undefined, "ابدأ الاستماع")} disabled={busy}
                onClick={() => (listening ? sp.stop() : sp.start())}>
                <i className={`bi ${listening ? "bi-stop-fill" : "bi-mic-fill"}`} aria-hidden="true" />
            </button>
            <div aria-live="polite" className="small">{listening ? t("voice.listening_hint", undefined, "أستمع… تحدّث الآن.") : t("voice.idle_hint", undefined, "اضغط للتحدث، ويمكنك تعديل النص قبل الإرسال.")}</div>
            <div className="w-100 text-start">
                <label htmlFor="speech-text" className="form-label">{t("voice.transcript_label", undefined, "النص")}</label>
                <textarea id="speech-text" className="form-control" rows={3} dir="rtl" value={value} readOnly={listening} maxLength={VOICE_TRANSCRIPT_MAX} aria-describedby="speech-count"
                    onChange={(e) => sp.setText(e.target.value.slice(0, VOICE_TRANSCRIPT_MAX))} placeholder={t("voice.transcript_placeholder", undefined, "سيظهر هنا ما تقوله")} />
                <div id="speech-count" className={`form-text text-end${near ? " fw-semibold" : ""}`} data-testid="speech-count"><bdi dir="ltr">{value.length} / {VOICE_TRANSCRIPT_MAX}</bdi></div>
                {sp.interim ? <div className="form-text" data-testid="speech-interim">…{sp.interim}</div> : null}
            </div>
            <div className="d-flex flex-wrap gap-2">
                <button type="button" className="btn btn-primary" style={{ minHeight: 48 }} disabled={!text || busy || listening} onClick={() => onSubmit(text)}>{t("voice.to_draft", undefined, "تحويل إلى مسودة")}</button>
                {text && !listening ? <button type="button" className="btn btn-outline-primary" style={{ minHeight: 48 }} onClick={sp.reset}>{t("voice.clear", undefined, "مسح")}</button> : null}
            </div>
        </div>
    );
}
