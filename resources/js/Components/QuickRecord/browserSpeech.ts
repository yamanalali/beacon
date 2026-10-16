import { useCallback, useEffect, useRef, useState } from "react";

/**
 * Optional in-browser speech recognition (Web Speech API).
 *
 * TODO: BUSINESS DECISION REQUIRED - Chrome/Edge send the audio to Google's servers for recognition.
 * This is OFF by default, only enabled per device after the user accepts the notice, and must not be
 * merged until Yaman approves sending audio to a third party.
 */

export const SPEECH_LANG = "ar-SY";
export const SPEECH_PREF_KEY = "beacon.voice.browserSpeech";

type Alt = { transcript: string };
type Result = { isFinal: boolean; length: number; [i: number]: Alt };
export type SpeechEvent = { resultIndex: number; results: { length: number; [i: number]: Result } };
export type SpeechErrorEvent = { error: string };

export interface SpeechRecognitionLike {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    onresult: ((e: SpeechEvent) => void) | null;
    onerror: ((e: SpeechErrorEvent) => void) | null;
    onend: (() => void) | null;
    start(): void;
    stop(): void;
    abort(): void;
}
export type SpeechRecognitionCtor = new () => SpeechRecognitionLike;

export function getSpeechRecognition(w: unknown = typeof window !== "undefined" ? window : undefined): SpeechRecognitionCtor | null {
    const win = w as { SpeechRecognition?: SpeechRecognitionCtor; webkitSpeechRecognition?: SpeechRecognitionCtor } | undefined;
    return win?.SpeechRecognition ?? win?.webkitSpeechRecognition ?? null;
}

export function readSpeechPref(storage: Pick<Storage, "getItem"> | undefined = safeStorage()): boolean {
    try { return storage?.getItem(SPEECH_PREF_KEY) === "on"; } catch { return false; }
}

export function writeSpeechPref(on: boolean, storage: Pick<Storage, "setItem" | "removeItem"> | undefined = safeStorage()): void {
    try { if (on) storage?.setItem(SPEECH_PREF_KEY, "on"); else storage?.removeItem(SPEECH_PREF_KEY); } catch { /* private mode */ }
}

function safeStorage(): Storage | undefined {
    try { return typeof window !== "undefined" ? window.localStorage : undefined; } catch { return undefined; }
}

export type SpeechState = "idle" | "listening" | "denied" | "error";

/** Live recognition: `text` is final + interim; editing it (setText) is kept as the new final base. */
export function useBrowserSpeech(Ctor: SpeechRecognitionCtor | null) {
    const [state, setState] = useState<SpeechState>("idle");
    const [finalText, setFinalText] = useState("");
    const [interim, setInterim] = useState("");
    const rec = useRef<SpeechRecognitionLike | null>(null);
    const base = useRef("");

    const stop = useCallback(() => { rec.current?.stop(); }, []);

    const start = useCallback(() => {
        if (!Ctor || rec.current) return;
        const r = new Ctor();
        r.lang = SPEECH_LANG;
        r.continuous = true;
        r.interimResults = true;
        base.current = finalText;
        r.onresult = (e) => {
            let fin = "";
            let tmp = "";
            for (let i = 0; i < e.results.length; i++) {
                const t = e.results[i][0]?.transcript ?? "";
                if (e.results[i].isFinal) fin += t; else tmp += t;
            }
            const joined = [base.current, fin.trim()].filter(Boolean).join(" ");
            setFinalText(joined);
            setInterim(tmp.trim());
        };
        r.onerror = (e) => setState(e.error === "not-allowed" || e.error === "service-not-allowed" ? "denied" : e.error === "no-speech" || e.error === "aborted" ? "idle" : "error");
        r.onend = () => {
            rec.current = null;
            setInterim("");
            setState((s) => (s === "listening" ? "idle" : s));
        };
        rec.current = r;
        setState("listening");
        try { r.start(); } catch { rec.current = null; setState("error"); }
    }, [Ctor, finalText]);

    useEffect(() => () => { rec.current?.abort(); rec.current = null; }, []);

    const setText = (t: string) => { setFinalText(t); base.current = t; setInterim(""); };
    const text = [finalText, interim].filter(Boolean).join(" ");
    return { state, text, interim, start, stop, setText, reset: () => setText("") };
}
