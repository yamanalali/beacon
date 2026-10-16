#!/usr/bin/env python3
"""Word error rate for whisper-benchmark.sh. Light Arabic normalisation (hamza/ta-marbuta/diacritics/punctuation)."""
import re, sys, pathlib

def norm(s):
    s = re.sub(r'[\u064B-\u0652\u0640]', '', s)
    s = re.sub('[إأآ]', 'ا', s).replace('ة', 'ه').replace('ى', 'ي')
    s = re.sub(r'[^\w\s]', ' ', s)
    return s.split()

def wer(r, h):
    d = list(range(len(h) + 1))
    for i, rw in enumerate(r, 1):
        prev, d[0] = d[0], i
        for j, hw in enumerate(h, 1):
            prev, d[j] = d[j], min(d[j] + 1, d[j-1] + 1, prev + (rw != hw))
    return d[len(h)]

refs = pathlib.Path(sys.argv[1]).read_text(encoding='utf-8').splitlines()
errs = words = 0
for i, ref in enumerate(refs, 1):
    f = pathlib.Path(sys.argv[2]) / f"{sys.argv[3]}-{i:02d}.txt"
    hyp = f.read_text(encoding='utf-8') if f.exists() else ''
    errs += wer(norm(ref), norm(hyp)); words += len(norm(ref))
print(f"{100*errs/words:.1f}%")
