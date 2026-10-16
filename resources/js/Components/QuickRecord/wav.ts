/** Mono 16-bit PCM WAV encoder (whisper.cpp reads WAV, not webm). Pure and testable. */
export const TARGET_SAMPLE_RATE = 16000;

export function mergeChunks(chunks: Float32Array[]): Float32Array {
    const out = new Float32Array(chunks.reduce((n, c) => n + c.length, 0));
    let o = 0;
    for (const c of chunks) { out.set(c, o); o += c.length; }
    return out;
}

/** Simple averaging downsampler (input rate >= output rate). */
export function downsample(input: Float32Array, inRate: number, outRate = TARGET_SAMPLE_RATE): Float32Array {
    if (outRate >= inRate) return input;
    const ratio = inRate / outRate;
    const out = new Float32Array(Math.floor(input.length / ratio));
    for (let i = 0; i < out.length; i++) {
        const start = Math.floor(i * ratio), end = Math.min(input.length, Math.floor((i + 1) * ratio));
        let sum = 0;
        for (let j = start; j < end; j++) sum += input[j];
        out[i] = end > start ? sum / (end - start) : 0;
    }
    return out;
}

export function encodeWav(samples: Float32Array, sampleRate: number): ArrayBuffer {
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const v = new DataView(buffer);
    const str = (o: number, s: string) => { for (let i = 0; i < s.length; i++) v.setUint8(o + i, s.charCodeAt(i)); };
    str(0, "RIFF"); v.setUint32(4, 36 + samples.length * 2, true); str(8, "WAVE");
    str(12, "fmt "); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
    v.setUint32(24, sampleRate, true); v.setUint32(28, sampleRate * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true);
    str(36, "data"); v.setUint32(40, samples.length * 2, true);
    for (let i = 0; i < samples.length; i++) {
        const s = Math.max(-1, Math.min(1, samples[i]));
        v.setInt16(44 + i * 2, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    }
    return buffer;
}

export function toWavBlob(chunks: Float32Array[], inRate: number): Blob {
    return new Blob([encodeWav(downsample(mergeChunks(chunks), inRate), Math.min(inRate, TARGET_SAMPLE_RATE))], { type: "audio/wav" });
}
