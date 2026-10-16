#!/usr/bin/env python3
"""Generate SYNTHETIC Arabic/English receipt images with ground truth (no real data).

Usage: python3 scripts/ocr-benchmark/generate.py [out_dir] [count] [seed]
Default out_dir: storage/app/ocr-benchmark (git-ignored).
Needs Pillow with raqm (Arabic shaping) and an Arabic TTF (Noto Sans Arabic/DejaVu).
"""
import json, os, random, sys, glob
from PIL import Image, ImageDraw, ImageFont, ImageFilter, ImageEnhance, features

out = sys.argv[1] if len(sys.argv) > 1 else 'storage/app/ocr-benchmark'
count = int(sys.argv[2]) if len(sys.argv) > 2 else 12
rnd = random.Random(int(sys.argv[3]) if len(sys.argv) > 3 else 42)
os.makedirs(out, exist_ok=True)

def font_path():
    for pat in ['/usr/share/fonts/**/NotoSansArabic*.ttf', '/usr/share/fonts/**/NotoNaskhArabic*.ttf', '/usr/share/fonts/**/Amiri*.ttf', '/usr/share/fonts/**/DejaVuSans.ttf', 'C:/Windows/Fonts/arial.ttf']:
        hits = sorted(glob.glob(pat, recursive=True))
        if hits:
            return hits[0]
    raise SystemExit('No suitable font found')

FONT = font_path()
ENGINE = ImageFont.Layout.RAQM if features.check('raqm') else ImageFont.Layout.BASIC
SHOPS_AR = ['سوبرماركت الأمل', 'مكتبة النور', 'صيدلية الشفاء', 'مطعم الياسمين', 'محل الريان', 'فرن السلام']
SHOPS_EN = ['ACME Market', 'Blue Cafe', 'City Pharmacy', 'Star Bakery', 'Green Grocer', 'Nova Books']
ITEMS_AR = ['خبز', 'حليب', 'سكر', 'شاي', 'رز', 'زيت']
ITEMS_EN = ['Bread', 'Milk', 'Sugar', 'Tea', 'Rice', 'Oil']

def receipt(i):
    ar = i % 2 == 0
    cur = rnd.choice(['SYP', 'USD'])
    shop = rnd.choice(SHOPS_AR if ar else SHOPS_EN)
    d, m = rnd.randint(1, 28), rnd.randint(1, 12)
    date_iso = f'2026-{m:02d}-{d:02d}'
    n = rnd.randint(2, 3)
    if cur == 'SYP':
        prices = [rnd.randint(1, 40) * 500 for _ in range(n)]
    else:
        prices = [round(rnd.uniform(1, 30), 2) for _ in range(n)]
    total = round(sum(prices), 2)
    fmt = (lambda v: f'{v:,.0f}') if cur == 'SYP' else (lambda v: f'{v:.2f}')
    names = rnd.sample(ITEMS_AR if ar else ITEMS_EN, n)
    if ar:
        cur_txt = 'ل.س' if cur == 'SYP' else 'دولار'
        lines = [shop, f'التاريخ: {d:02d}/{m:02d}/2026'] + [f'{nm} {fmt(p)}' for nm, p in zip(names, prices)] + [f'الإجمالي: {fmt(total)} {cur_txt}']
    else:
        lines = [shop, f'Date: {d:02d}/{m:02d}/2026'] + [f'{nm} {fmt(p)}' for nm, p in zip(names, prices)] + [f'Total: {fmt(total)} {cur}']
    amount = (f'{total:.2f}'.rstrip('0').rstrip('.'))
    return lines, {'amount': amount, 'date': date_iso, 'currency': cur, 'counterparty': shop, 'text': '\n'.join(lines), 'lang': 'ar' if ar else 'en'}

def render(lines, scale=1.0):
    f = ImageFont.truetype(FONT, int(34 * scale), layout_engine=ENGINE)
    w, lh = int(760 * scale), int(60 * scale)
    im = Image.new('L', (w, lh * len(lines) + int(60 * scale)), 255)
    dr = ImageDraw.Draw(im)
    for k, t in enumerate(lines):
        y = int(30 * scale) + k * lh
        if any('\u0600' <= c <= '\u06ff' for c in t):
            dr.text((w - int(30 * scale), y), t, font=f, fill=0, anchor='ra', direction='rtl')
        else:
            dr.text((int(30 * scale), y), t, font=f, fill=0)
    return im

VARIANTS = {
    'clean': lambda im: im,
    'rotated': lambda im: im.rotate(rnd.choice([-6, -4, 4, 6]), expand=True, fillcolor=255),
    'upside': lambda im: im.rotate(180, expand=True, fillcolor=255),
    'lowcontrast': lambda im: ImageEnhance.Contrast(im).enhance(0.25).point(lambda p: p * 0.6 + 90),
    'small': lambda im: im.resize((im.width // 3, im.height // 3), Image.BILINEAR),
    'noise': lambda im: Image.blend(im.filter(ImageFilter.GaussianBlur(1.0)), Image.effect_noise(im.size, 80).convert('L'), 0.3),
}

truth = []
for i in range(count):
    lines, gt = receipt(i)
    base = render(lines)
    for v, fn in VARIANTS.items():
        name = f'r{i:02d}_{v}.png'
        fn(base.copy()).convert('L').save(os.path.join(out, name), optimize=True)
        truth.append({'file': name, 'variant': v, **gt})
json.dump(truth, open(os.path.join(out, 'truth.json'), 'w'), ensure_ascii=False, indent=1)
print(f'{len(truth)} images -> {out}')
