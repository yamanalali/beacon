import { describe, expect, it } from "vitest";
import { downsample, encodeWav, mergeChunks, toWavBlob } from "./wav";

describe("wav", () => {
    it("writes a valid mono 16-bit PCM header", () => {
        const v = new DataView(encodeWav(new Float32Array([0, 1, -1]), 16000));
        const s = (o: number, n: number) => String.fromCharCode(...Array.from({ length: n }, (_, i) => v.getUint8(o + i)));
        expect(s(0, 4)).toBe("RIFF"); expect(s(8, 4)).toBe("WAVE"); expect(s(36, 4)).toBe("data");
        expect(v.getUint16(22, true)).toBe(1); expect(v.getUint32(24, true)).toBe(16000); expect(v.getUint16(34, true)).toBe(16);
        expect(v.getUint32(40, true)).toBe(6);
        expect(v.getInt16(46, true)).toBe(0x7fff); expect(v.getInt16(48, true)).toBe(-0x8000);
    });
    it("merges and downsamples to 16 kHz", () => {
        expect(mergeChunks([new Float32Array([1, 2]), new Float32Array([3])])).toEqual(new Float32Array([1, 2, 3]));
        expect(downsample(new Float32Array(48000), 48000).length).toBe(16000);
        const b = toWavBlob([new Float32Array(4800)], 48000);
        expect(b.type).toBe("audio/wav"); expect(b.size).toBe(44 + 1600 * 2);
    });
});
