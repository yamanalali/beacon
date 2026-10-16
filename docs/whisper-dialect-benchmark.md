# Whisper Levantine (Syrian) benchmark

Script: `scripts/whisper-benchmark.sh` (+ `whisper-wer.py`, `whisper-benchmark-sentences.txt`).
10 Syrian-dialect bookkeeping sentences, whisper.cpp `0c7be69`, `-l ar`, 8 vCPU Xeon (CPU only), 2026-10-16.

**Audio is synthetic** (espeak-ng `ar`, robotic MSA-style voice, no real people). It is far harder
than a real human voice, so absolute WER is pessimistic; use it to *compare* models/prompts only.
Re-run with real recordings via `AUDIO_DIR=` (keep them outside the repo).

Prompt used: `دفعت، قبضت، مصاري، ليرة، دولار، إيجار، محل، كل شهر، الاف، مية، ألف، كهربا، مازوت، فاتورة`

| model | size | prompt | WER | WER + money corrector | time (10 clips) |
|---|---|---|---|---|---|
| small | 466 MB | no | 84.2% | 70.2% | 69 s |
| small | | yes | 73.7% | 68.4% | 115 s |
| medium | 1.5 GB | no | 84.2% | 80.7% | 137 s |
| medium | | yes | 63.2% | 61.4% | 104 s |
| large-v3-turbo | 1.6 GB | no | 87.7% | 68.4% | 73 s |
| **large-v3-turbo** | | **yes** | **61.4%** | **50.9%** | **76 s** |

Findings
- large-v3-turbo + prompt is best and about as fast as small (turbo has only 4 decoder layers). It is the default (manager decision).
- Without a prompt, large models hallucinate YouTube phrases on unclear audio («اشتركوا في القناة»).
- A comma-list prompt can leak its words into the text (small wrote «قبضت، محل، كل شهر» for a sentence without them), so the prompt stays opt-in.
- `WhisperMoneyCorrector` was tuned on these same outputs, so its gain is optimistic.
- On Windows a non-ASCII `WHISPER_PROMPT` is skipped (whisper-cli reads argv in the ANSI code page), so an Arabic prompt has no effect there.

TODO: BUSINESS DECISION REQUIRED — non-empty default `WHISPER_PROMPT` (benchmark suggests it helps on Linux; #53 kept it empty).
