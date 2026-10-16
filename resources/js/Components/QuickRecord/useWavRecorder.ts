import { useCallback, useEffect, useRef, useState } from "react";
import { toWavBlob } from "./wav";

export const MAX_SECONDS = 60;
type State = "idle" | "recording" | "denied" | "unsupported";

/** Records microphone audio with Web Audio and returns a mono 16 kHz WAV blob. */
export function useWavRecorder() {
    const [state, setState] = useState<State>("idle");
    const [seconds, setSeconds] = useState(0);
    const ctx = useRef<AudioContext | null>(null);
    const stream = useRef<MediaStream | null>(null);
    const node = useRef<ScriptProcessorNode | null>(null);
    const chunks = useRef<Float32Array[]>([]);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);
    const resolver = useRef<((b: Blob | null) => void) | null>(null);

    const cleanup = useCallback(() => {
        if (timer.current) clearInterval(timer.current);
        node.current?.disconnect();
        stream.current?.getTracks().forEach((t) => t.stop());
        void ctx.current?.close().catch(() => {});
        node.current = null; stream.current = null; ctx.current = null;
    }, []);

    const stop = useCallback((): Promise<Blob | null> => {
        const rate = ctx.current?.sampleRate ?? 48000;
        const blob = chunks.current.length ? toWavBlob(chunks.current, rate) : null;
        chunks.current = [];
        cleanup();
        setState("idle");
        resolver.current?.(blob);
        resolver.current = null;
        return Promise.resolve(blob);
    }, [cleanup]);

    const start = useCallback(async () => {
        const AC = (window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext);
        if (!navigator.mediaDevices?.getUserMedia || !AC) { setState("unsupported"); return; }
        try {
            stream.current = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true } });
        } catch {
            setState("denied");
            return;
        }
        ctx.current = new AC();
        const source = ctx.current.createMediaStreamSource(stream.current);
        node.current = ctx.current.createScriptProcessor(4096, 1, 1);
        chunks.current = [];
        node.current.onaudioprocess = (e) => chunks.current.push(new Float32Array(e.inputBuffer.getChannelData(0)));
        source.connect(node.current);
        node.current.connect(ctx.current.destination);
        setSeconds(0);
        setState("recording");
        timer.current = setInterval(() => setSeconds((s) => {
            if (s + 1 >= MAX_SECONDS) void stop();
            return s + 1;
        }), 1000);
    }, [stop]);

    useEffect(() => () => cleanup(), [cleanup]);
    return { state, seconds, start, stop, cancel: () => { chunks.current = []; cleanup(); setState("idle"); } };
}
