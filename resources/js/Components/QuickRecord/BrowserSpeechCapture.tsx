import { useState } from "react";
import { readSpeechPref, useBrowserSpeech, writeSpeechPref, type SpeechRecognitionCtor } from "./browserSpeech";

/** Toggle (with Google notice) shown only when the browser has SpeechRecognition. OFF by default. */
export function BrowserSpeechToggle({ enabled, onChange }: { enabled: boolean; onChange: (on: boolean) => void }) {
    const [asking, setAsking] = useState(false);
    return (
        <div className="w-100 text-start" data-testid="speech-toggle">
            <div className="form-check form-switch d-flex align-items-center gap-2 justify-content-center">
                <input id="browser-speech" className="form-check-input" type="checkbox" role="switch" checked={enabled}
                    onChange={(e) => { if (e.target.checked) setAsking(true); else { writeSpeechPref(false); onChange(false); } }} />
                <label className="form-check-label small" htmlFor="browser-speech">التعرّف على الكلام في المتصفح (تجريبي)</label>
            </div>
            {asking && !enabled ? (
                <div className="alert alert-warning mt-2 mb-0 small" role="alertdialog" aria-labelledby="speech-notice-title" data-testid="speech-notice">
                    <strong id="speech-notice-title" className="d-block mb-1">قبل التفعيل</strong>
                    سيرسل المتصفح صوتك إلى خوادم Google لتحويله إلى نص، ولا يمرّ عبر بيكون. سيُحفظ اختيارك على هذا الجهاز فقط ويمكنك إيقافه في أي وقت.
                    <div className="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => { writeSpeechPref(true); setAsking(false); onChange(true); }}>أوافق، فعّل</button>
                        <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => setAsking(false)}>إلغاء</button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

export const initialSpeechEnabled = (Ctor: SpeechRecognitionCtor | null) => Ctor !== null && readSpeechPref();

/** Live transcript in an editable textarea; submit sends the text into the voice-draft flow. */
export function BrowserSpeechCapture({ Ctor, onSubmit, busy }: { Ctor: SpeechRecognitionCtor; onSubmit: (text: string) => void; busy?: boolean }) {
    const sp = useBrowserSpeech(Ctor);
    const listening = sp.state === "listening";
    const text = sp.text.trim();
    return (
        <div className="d-flex flex-column align-items-center gap-3 w-100" data-testid="browser-speech">
            {sp.state === "denied" ? <div className="alert alert-warning mb-0 w-100" role="alert">لم يُسمح باستخدام الميكروفون. فعّله من إعدادات المتصفح ثم حاول مرة أخرى.</div> : null}
            {sp.state === "error" ? <div className="alert alert-warning mb-0 w-100" role="alert">تعذّر التعرّف على الكلام. حاول مرة أخرى أو أوقف الخيار واستخدم التسجيل العادي.</div> : null}
            <button type="button" className={`btn ${listening ? "btn-danger" : "btn-primary"} rounded-circle`} style={{ width: 88, height: 88, fontSize: 32 }}
                aria-pressed={listening} aria-label={listening ? "إيقاف الاستماع" : "ابدأ الاستماع"} disabled={busy}
                onClick={() => (listening ? sp.stop() : sp.start())}>
                <i className={`bi ${listening ? "bi-stop-fill" : "bi-mic-fill"}`} aria-hidden="true" />
            </button>
            <div aria-live="polite" className="small">{listening ? "أستمع… تحدّث الآن." : "اضغط للتحدث، ويمكنك تعديل النص قبل الإرسال."}</div>
            <div className="w-100 text-start">
                <label htmlFor="speech-text" className="form-label">النص</label>
                <textarea id="speech-text" className="form-control" rows={3} dir="rtl" value={sp.text} readOnly={listening}
                    onChange={(e) => sp.setText(e.target.value)} placeholder="سيظهر هنا ما تقوله" />
                {sp.interim ? <div className="form-text" data-testid="speech-interim">…{sp.interim}</div> : null}
            </div>
            <div className="d-flex flex-wrap gap-2">
                <button type="button" className="btn btn-primary" style={{ minHeight: 48 }} disabled={!text || busy || listening} onClick={() => onSubmit(text)}>تحويل إلى مسودة</button>
                {text && !listening ? <button type="button" className="btn btn-outline-primary" style={{ minHeight: 48 }} onClick={sp.reset}>مسح</button> : null}
            </div>
        </div>
    );
}
