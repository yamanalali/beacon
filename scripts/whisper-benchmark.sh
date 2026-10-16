#!/usr/bin/env bash
# Local whisper.cpp benchmark for Levantine (Syrian) bookkeeping sentences.
# Synthetic audio only (espeak-ng "ar" TTS): NO recordings of real people are used or committed.
# Usage: WHISPER_CLI=/path/whisper-cli MODELS_DIR=/path/models OUT=/tmp/wb scripts/whisper-benchmark.sh
# Optional: AUDIO_DIR=dir with 01.wav..NN.wav (16 kHz mono) to replace TTS (keep outside the repo).
set -euo pipefail
WHISPER_CLI=${WHISPER_CLI:-whisper-cli}
MODELS_DIR=${MODELS_DIR:-./models}
MODELS=${MODELS:-"small medium large-v3-turbo"}
OUT=${OUT:-/tmp/whisper-bench}
THREADS=${THREADS:-8}
PROMPT=${PROMPT:-"دفعت، قبضت، مصاري، ليرة، دولار، إيجار، محل، كل شهر، الاف، مية، ألف، كهربا، مازوت، فاتورة"}
HERE=$(cd "$(dirname "$0")" && pwd)
SENTENCES="$HERE/whisper-benchmark-sentences.txt"
mkdir -p "$OUT/audio" "$OUT/hyp"
mapfile -t REFS < "$SENTENCES"
for i in "${!REFS[@]}"; do
  n=$(printf %02d $((i+1)))
  if [ -n "${AUDIO_DIR:-}" ]; then cp "$AUDIO_DIR/$n.wav" "$OUT/audio/$n.wav"; continue; fi
  [ -f "$OUT/audio/$n.wav" ] && continue
  espeak-ng -v ar -s 140 -w "$OUT/audio/raw$n.wav" "${REFS[$i]}"
  ffmpeg -loglevel error -y -i "$OUT/audio/raw$n.wav" -ar 16000 -ac 1 "$OUT/audio/$n.wav" && rm "$OUT/audio/raw$n.wav"
done
echo "model,prompt,seconds,wer"
for m in $MODELS; do
  for p in no yes; do
    args=(); [ $p = yes ] && args=(--prompt "$PROMPT")
    start=$(date +%s.%N)
    for i in "${!REFS[@]}"; do
      n=$(printf %02d $((i+1)))
      "$WHISPER_CLI" -m "$MODELS_DIR/ggml-$m.bin" -f "$OUT/audio/$n.wav" -l ar -t "$THREADS" -nt -np -otxt -of "$OUT/hyp/$m-$p-$n" "${args[@]}" >/dev/null 2>&1
    done
    secs=$(echo "$(date +%s.%N) - $start" | bc)
    wer=$(python3 "$HERE/whisper-wer.py" "$SENTENCES" "$OUT/hyp" "$m-$p")
    printf '%s,%s,%.1f,%s\n' "$m" "$p" "$secs" "$wer"
  done
done
