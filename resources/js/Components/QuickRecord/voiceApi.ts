import { handleInertiaConflict } from "../../lib/inertiaConflict";
import type { BookkeepingOptions } from "../../types/bookkeeping";
import type { CategorySuggestion, TransactionDraft } from "../Transactions/ReviewForm";

/** Where the voice text came from: whisper on the server, or the browser's speech API. */
export type VoiceInputSource = "audio" | "browser_speech";

/** Body for POST voice-drafts: EITHER an `audio` file OR a `transcript` (max 1000 chars) with `source`. */
export type VoiceTranscriptRequest = { transcript: string; source: Exclude<VoiceInputSource, "audio"> };

export const VOICE_TRANSCRIPT_MAX = 1000;

export type VoiceDraftResponse = {
    draft: TransactionDraft & { raw_text?: string | null; source?: string; input_source?: VoiceInputSource };
    type: string;
    transaction_type_options?: { value: string; label: string }[];
    category_suggestion: CategorySuggestion;
    options: BookkeepingOptions;
    ai_available?: boolean;
    message?: string;
    confirm: { method?: string; url: string };
};

export type VoiceResult =
    | { ok: true; data: VoiceDraftResponse }
    | { ok: false; kind: "unavailable" | "invalid" | "error" | "unsupported_text"; message: string };

function xsrf(): string | null {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : null;
}

/** POST the WAV to the voice endpoint. 404/503 => voice unavailable (feature off or STT down). */
export async function sendVoice(url: string, audio: Blob, fetchImpl: typeof fetch = fetch): Promise<VoiceResult> {
    const body = new FormData();
    body.append("audio", audio, "recording.wav");
    const headers: Record<string, string> = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    const token = xsrf();
    if (token) headers["X-XSRF-TOKEN"] = token;
    let res: Response;
    try {
        res = await fetchImpl(url, { method: "POST", body, headers, credentials: "same-origin" });
    } catch {
        return { ok: false, kind: "error", message: "تعذّر الاتصال. تحقق من الشبكة وحاول مرة أخرى." };
    }
    if (res.status === 409 && handleInertiaConflict(res)) return { ok: false, kind: "error", message: "" };
    if (res.status === 404 || res.status === 503) return { ok: false, kind: "unavailable", message: "التسجيل الصوتي غير متاح حالياً. يمكنك إدخال العملية يدوياً." };
    if (res.status === 422) {
        const j = (await res.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;
        return { ok: false, kind: "invalid", message: j?.errors?.audio?.[0] ?? "تعذّرت قراءة التسجيل. حاول مرة أخرى." };
    }
    if (!res.ok) return { ok: false, kind: "error", message: "حدث خطأ أثناء معالجة التسجيل. حاول مرة أخرى." };
    return { ok: true, data: (await res.json()) as VoiceDraftResponse };
}

/**
 * Browser speech: send recognised text instead of audio.
 * TODO: BUSINESS DECISION REQUIRED (see browserSpeech.ts). The endpoint currently requires `audio`;
 * this needs the backend to accept an optional `transcript` (string, max ~1000) when `audio` is absent,
 * skip STT and feed it to VoiceDraftParser. Until then a 422 on `audio` is reported as unavailable.
 */
export async function sendTranscript(url: string, transcript: string, fetchImpl: typeof fetch = fetch): Promise<VoiceResult> {
    const body = new FormData();
    body.append("transcript", transcript);
    body.append("source", "browser_speech");
    const headers: Record<string, string> = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    const token = xsrf();
    if (token) headers["X-XSRF-TOKEN"] = token;
    let res: Response;
    try {
        res = await fetchImpl(url, { method: "POST", body, headers, credentials: "same-origin" });
    } catch {
        return { ok: false, kind: "error", message: "تعذّر الاتصال. تحقّق من الشبكة وحاول مرة أخرى." };
    }
    if (res.status === 409 && handleInertiaConflict(res)) return { ok: false, kind: "error", message: "" };
    if (res.status === 404 || res.status === 503) return { ok: false, kind: "unavailable", message: "التسجيل الصوتي غير متاح حالياً. يمكنك الإدخال اليدوي." };
    if (res.status === 422) {
        const j = (await res.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;
        if (j?.errors?.audio && !j.errors.transcript) {
            return { ok: false, kind: "unsupported_text", message: "الخادم لا يقبل النص المكتوب من المتصفح بعد. استخدم التسجيل العادي." };
        }
        return { ok: false, kind: "invalid", message: j?.errors?.transcript?.[0] ?? "تعذّر فهم النص. عدّله وحاول مرة أخرى." };
    }
    if (!res.ok) return { ok: false, kind: "error", message: "حدث خطأ أثناء معالجة النص. حاول مرة أخرى." };
    return { ok: true, data: (await res.json()) as VoiceDraftResponse };
}
