# Beacon

نظام Laravel 12 مع Inertia وReact لمسك الدفاتر، وتقييم الجاهزية، والمطابقة مع مؤسسات التمويل الأصغر.
القرارات المعتمدة موجودة بـ[`BEACON-DECISIONS.md`](BEACON-DECISIONS.md).

## قائمة المتطلبات

آخر تحديث: 16 أكتوبر 2026، و`main` على `4efff95`.

### ✅ انتهى

- [x] ريستايل الواجهة ونظام التصميم الموحد (#13، #61)
- [x] البنود بالمعاملات: الواجهة (#58) والـAPI (#59)
- [x] إصلاح تست الـwhisper prompt حتى ما يعتمد على `.env` (#60)
- [x] توثيق القرارات بـ`BEACON-DECISIONS.md` (#62، #64)
- [x] snapshots لتقييمات الجاهزية وتثبيت النتيجة (#63، #64)
- [x] Scoring v2، الجزء الأول: المحاور والفئات وأنواع المعايير (#65)

### 🔄 قيد المراجعة

- [ ] البنود من الصوت وقاعدة «الاف» وتحذيرات المبلغ (#66، `feature/voice-line-items`)

### 🛠️ قيد التنفيذ

- [ ] صلاحيات السايد بار والقائمة على الموبايل حسب `auth.can` (`fix/sidebar-permissions`)
- [ ] بنود الـOCR وأخذ المجموع من سطر «اجمالي» (`feature/ocr-line-items`)
- [ ] Scoring engine API: CRUD للمسودات، والنسخ، والاستيراد والتصدير، والمعاينة، والتحقق قبل النشر (`feature/scoring-engine-api`)
- [ ] واجهة بناء الـscoring matrix والمعايير (`feature/scoring-builder-ui`)
- [ ] عرض تحذيرات المبلغ بشاشة المراجعة (`feature/review-amount-warning`)
- [ ] تدقيق عزل البيانات بين الـtenants (`fix/tenant-isolation-audit`)

### ⏳ بالانتظار

- [ ] كلمات البائع العامة متل «زبون» (`fix/generic-vendor-words`)
- [ ] صفحات تسجيل الدخول على نظام #13 مع إبقاء شكل البطاقة
- [ ] صفحات Mfi والمطابقة، وصفحة الخطأ، وصفحات الأدمن على نظام #13
- [ ] عرض الفئة (`band`) بحزمة الإحالة
- [ ] اقتراحات تقييم بالذكاء الاصطناعي
- [ ] مقارنة Whisper بين `small` و`medium` (بدها تسجيل من يمان)

## التشغيل محلياً

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
composer run dev
```

الاختبارات: `php artisan test` و`npx vitest run`.
## بنود الفاتورة من الـOCR (اقتراحات)

مسودة الإيصال فيها `suggested_items: [{description, quantity, unit_price, total}]`: الأرقام أرقام، والقيمة يلي ما انقرت `null`، وإذا ما في بنود `[]`. وفيها كمان `items_mismatch` (تحذير بس، ما بيمنع الحفظ) و`suggested_invoice_number` و`suggested_amount_warning`. المبلغ بيجي بس من سطر «اجمالي/الإجمالي/المجموع»، وإذا ما انقرا بيصير مجموع البنود، وإذا ما في بنود بيبقى فاضي مع تحذير. ما بينحفظ شي قبل التأكيد.

- `AI_OCR_LINE_PASS=true`: قراءة كل سطر لحالو (`--psm 7`)، وبترجّع العناوين القصيرة متل «بيتزا 1».
- `AI_OCR_TABLE_PASS=true`: قارئ الفواتير المسطّرة (م | البيان | الكمية | السعر | القيمة) بـPHP GD: بيلاقي خطوط الجدول، وبيربط الأعمدة من سطر العناوين، وبيقرا خلايا الأرقام بـwhitelist أرقام، وبيتأكد إنو الكمية × السعر = القيمة.

Benchmark (صور اصطناعية، مو جزء من التستات):

```
python3 scripts/ocr-benchmark/generate.py storage/app/ocr-benchmark 12 42 8 4   # إيصالات + بنود + فواتير جدول
php artisan ocr:benchmark storage/app/ocr-benchmark --by-variant
python3 scripts/ocr-benchmark/generate.py --table-fixture tests/fixtures/ocr/table-invoice-synthetic.png
```
