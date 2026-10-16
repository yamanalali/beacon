#!/usr/bin/env python3
"""Generate SYNTHETIC Arabic/English receipt images with ground truth (no real data).

Usage: python3 scripts/ocr-benchmark/generate.py [out_dir] [count] [seed] [items_count] [table_count]
items_count (default 0) appends extra line-item receipts ("name qty unit total",
"qty x name price", Arabic-Indic digits, short "shop N" headers) drawn from a separate
random stream, so the base set (and its amount/date/currency numbers) is unchanged.
table_count (default 0) appends ruled-table Arabic sales invoices (م | البيان | الكمية |
السعر | القيمة, shaded header, empty rows, «اجمالي الفاتورة» row). All names/numbers are
invented.
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

INVOICE_SHOPS = ['مطعم الزيتون 2', 'بيتزا النجمة 3', 'مقهى الواحة 1']
ADDRESSES = ['شارع تجريبي - حي الاختبار - مدينة وهمية', 'طريق المثال - منطقة العرض']

def num(v):
    v = round(v, 2)
    return int(v) if v == int(v) else v

AR_DIGITS = str.maketrans('0123456789.,', '٠١٢٣٤٥٦٧٨٩٫٬')
ITEM_SHOPS = ['بيتزا 1', 'فلافل 2', 'شاورما 3', 'Cafe 7']
ITEM_NAMES_AR = ['بيتزا', 'فلافل', 'شاورما', 'كولا', 'بطاطا', 'حمص']
ITEM_NAMES_EN = ['Pizza', 'Burger', 'Cola', 'Fries', 'Salad', 'Coffee']

def items_receipt(r, i):
    # Synthetic line-item receipt: short 'shop N' header and multi-column item lines.
    ar = i % 2 == 0
    shop = r.choice(ITEM_SHOPS[:3] if ar else ITEM_SHOPS[3:])
    d, m = r.randint(1, 28), r.randint(1, 12)
    names = r.sample(ITEM_NAMES_AR if ar else ITEM_NAMES_EN, 3)
    items, lines = [], [shop, f'{d:02d}/{m:02d}/2026']
    arabic_digits = ar and i % 4 == 0
    for k, nm in enumerate(names):
        q, u = r.randint(1, 4), r.randint(2, 30) * 500
        t = q * u
        if k % 2 == 0:
            line = f'{nm} {q} {u:,} {t:,}'
        else:
            line = f'{q} x {nm} {t:,}'
        lines.append(line.translate(AR_DIGITS) if arabic_digits else line)
        items.append({'description': nm, 'quantity': q, 'unit_price': u if k % 2 == 0 or q == 1 else None, 'total': t})
    total = sum(x['total'] for x in items)
    tl = (f'الإجمالي {total:,} ل.س' if ar else f'Total {total:,} SYP')
    lines.append(tl.translate(AR_DIGITS) if arabic_digits else tl)
    return lines, {'items': items, 'amount': str(total), 'date': f'2026-{m:02d}-{d:02d}', 'currency': 'SYP', 'counterparty': shop, 'text': '\n'.join(lines), 'lang': 'items'}

def invoice(i):
    # Arabic restaurant invoice: header, address, items with decimals, 'المبلغ المستحق', m/d/yy date, no currency.
    shop = rnd.choice(INVOICE_SHOPS)
    d, m = rnd.randint(13, 28), rnd.randint(1, 12)  # day > 12 keeps m/d/yy unambiguous
    prices = [round(rnd.uniform(2, 30), 2) for _ in range(2)]
    total = round(sum(prices), 2)
    names = rnd.sample(['سلطة الجبنة', 'عصير البطيخ', 'شاي بالنعناع', 'فطيرة زعتر'], 2)
    lines = [shop, rnd.choice(ADDRESSES), 'فاتورة', 'أمين الصندوق: المالك']
    for nm, p in zip(names, prices):
        lines += [f'{nm} {p:.2f}', f'{p:.2f} x 1']
    lines += [f'المبلغ المستحق {total:.2f}', f'{m}/{d}/21, 4:56 PM']
    amount = f'{total:.2f}'.rstrip('0').rstrip('.')
    items = [{'description': nm, 'quantity': 1, 'unit_price': num(p), 'total': num(p)} for nm, p in zip(names, prices)]
    return lines, {'items': items, 'amount': amount, 'date': f'2021-{m:02d}-{d:02d}', 'currency': None, 'counterparty': shop, 'text': '\n'.join(lines), 'lang': 'ar-invoice'}

def receipt(i):
    if i % 3 == 2:
        return invoice(i)
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
    items = [{'description': nm, 'quantity': None, 'unit_price': None, 'total': num(p)} for nm, p in zip(names, prices)]
    return lines, {'items': items, 'amount': amount, 'date': date_iso, 'currency': cur, 'counterparty': shop, 'text': '\n'.join(lines), 'lang': 'ar' if ar else 'en'}

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
TABLE_SHOPS = ['شركة النخبة للالكترونيات', 'مؤسسة الفجر للتجهيزات', 'محلات الصفا للأدوات']
TABLE_CUSTOMERS = ['شركة الربيع التجارية', 'مؤسسة الشروق', 'مكتب الأفق للخدمات']
TABLE_ITEMS = ['حاسوب محمول', 'فأرة لاسلكية', 'لوحة مفاتيح', 'شاشة عرض', 'طابعة ملونة', 'كابل شبكة', 'ذاكرة تخزين', 'سماعات رأس']

def table_invoice(r, i):
    shop, cust = r.choice(TABLE_SHOPS), r.choice(TABLE_CUSTOMERS)
    d, m, y = r.randint(13, 28), r.randint(1, 12), r.randint(2021, 2026)
    no = r.randint(1000, 9999)
    rows = []
    for nm in r.sample(TABLE_ITEMS, r.randint(3, 5)):
        q, u = r.randint(1, 5), r.randint(1, 60) * 250
        rows.append((nm, q, u, q * u))
    total = sum(x[3] for x in rows)
    S = 2  # draw at 2x then downscale like a phone screenshot
    W = 830 * S
    f = ImageFont.truetype(FONT, 22 * S, layout_engine=ENGINE)
    fb = ImageFont.truetype(FONT, 28 * S, layout_engine=ENGINE)
    rh = 34 * S
    n_rows = 12
    H = (260 + 40 + n_rows * 34 + 60 + 60) * S
    im = Image.new('L', (W, H), 255)
    dr = ImageDraw.Draw(im)
    def rtl(x, y, t, font=f):
        dr.text((x, y), t, font=font, fill=0, anchor='mm', direction='rtl' if any('\u0600' <= c <= '\u06ff' for c in t) else None)
    dr.rectangle([60 * S, 30 * S, 770 * S, 80 * S], fill=200); rtl(W // 2, 55 * S, shop, fb)
    dr.rectangle([60 * S, 95 * S, 770 * S, 140 * S], fill=170); rtl(W // 2, 117 * S, 'فاتورة مبيعات', fb)
    dr.text((760 * S, 160 * S), f'رقم الفاتورة / {no}', font=f, fill=0, anchor='ra', direction='rtl')
    dr.text((70 * S, 160 * S), f'التاريخ / {d}/{m}/{y}', font=f, fill=0, anchor='la', direction='rtl')
    dr.text((760 * S, 205 * S), f'مطلوب من السادة / {cust}', font=f, fill=0, anchor='ra', direction='rtl')
    # columns right->left: م | البيان | الكمية | السعر | القيمة
    xs = [x * S for x in (60, 200, 340, 480, 690, 770)]  # left..right edges
    top = 260 * S
    dr.rectangle([xs[0], top, xs[-1], top + 40 * S], fill=215)
    heads = ['القيمة', 'السعر', 'الكمية', 'البيان', 'م']
    for k, h in enumerate(heads):
        rtl((xs[k] + xs[k + 1]) // 2, top + 20 * S, h)
    body = top + 40 * S
    for j in range(n_rows + 1):
        dr.line([xs[0], body + j * rh, xs[-1], body + j * rh], fill=215, width=S)
    for j, (nm, q, u, t) in enumerate(rows):
        y = body + j * rh + rh // 2
        for k, txt in enumerate([str(t), str(u), str(q), nm, str(j + 1)]):
            rtl((xs[k] + xs[k + 1]) // 2, y, txt)
    bottom = body + n_rows * rh
    dr.rectangle([xs[0], top, xs[-1], bottom], outline=0, width=2 * S)
    dr.line([xs[0], top + 40 * S, xs[-1], top + 40 * S], fill=120, width=S)
    for x in xs[1:-1]:
        dr.line([x, top, x, bottom], fill=0, width=2 * S)
    # total row: dark value cell + merged label
    tb = bottom + 50 * S
    dr.rectangle([xs[0], bottom, xs[1], tb], fill=150, outline=0, width=2 * S)
    dr.rectangle([xs[1], bottom, xs[-1], tb], outline=0, width=2 * S)
    rtl((xs[0] + xs[1]) // 2, bottom + 25 * S, str(total), fb)
    rtl((xs[1] + xs[-1]) // 2, bottom + 25 * S, 'اجمالي الفاتورة', fb)
    im = im.resize((W // S, H // S), Image.LANCZOS)
    items = [{'description': nm, 'quantity': q, 'unit_price': u, 'total': t} for nm, q, u, t in rows]
    text = '\n'.join([shop, 'فاتورة مبيعات', f'رقم الفاتورة / {no}', f'التاريخ / {d}/{m}/{y}', f'مطلوب من السادة / {cust}']
                     + [f'{j + 1} {nm} {q} {u} {t}' for j, (nm, q, u, t) in enumerate(rows)] + [f'اجمالي الفاتورة {total}'])
    return im, {'items': items, 'amount': str(total), 'date': f'{y}-{m:02d}-{d:02d}', 'currency': None, 'counterparty': cust,
                'invoice_number': str(no), 'type': 'income', 'text': text, 'lang': 'table'}

extra = int(sys.argv[4]) if len(sys.argv) > 4 else 0
irnd = random.Random(1000 + (int(sys.argv[3]) if len(sys.argv) > 3 else 42))
for i in range(extra):
    lines, gt = items_receipt(irnd, i)
    base = render(lines)
    for v, fn in VARIANTS.items():
        name = f'i{i:02d}_{v}.png'
        fn(base.copy()).convert('L').save(os.path.join(out, name), optimize=True)
        truth.append({'file': name, 'variant': v, **gt})
tables = int(sys.argv[5]) if len(sys.argv) > 5 else 0
trnd = random.Random(2000 + (int(sys.argv[3]) if len(sys.argv) > 3 else 42))
for i in range(tables):
    base, gt = table_invoice(trnd, i)
    for v, fn in VARIANTS.items():
        name = f't{i:02d}_{v}.png'
        fn(base.copy()).convert('L').save(os.path.join(out, name), optimize=True)
        truth.append({'file': name, 'variant': v, **gt})
json.dump(truth, open(os.path.join(out, 'truth.json'), 'w'), ensure_ascii=False, indent=1)
print(f'{len(truth)} images -> {out}')
