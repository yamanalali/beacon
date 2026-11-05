<?php

namespace App\Support;

use App\Models\ReadinessCriterion;
use InvalidArgumentException;
use ZipArchive;

/**
 * Example readiness rule-set for Beacon admins: JSON import + Excel planning workbook.
 * Content is illustrative only — not Beacon-approved scoring rules.
 */
class ReadinessExampleTemplate
{
    public static function definition(): array
    {
        return [
            'format' => ReadinessRuleSetValidator::FORMAT,
            'format_version' => ReadinessRuleSetValidator::FORMAT_VERSION,
            'name_ar' => 'مثال جاهزية — للمنشآت الصغيرة',
            'change_summary' => 'قالب تعليمي قابل للتعديل. استبدله بقواعد بيكون المعتمدة قبل الاعتماد.',
            'source_version_number' => null,
            'pillars' => [
                ['code' => 'finance', 'label_ar' => 'السجلات المالية', 'weight' => 40, 'min_score' => null, 'sort_order' => 1],
                ['code' => 'compliance', 'label_ar' => 'الامتثال والتراخيص', 'weight' => 30, 'min_score' => null, 'sort_order' => 2],
                ['code' => 'operations', 'label_ar' => 'التشغيل والإدارة', 'weight' => 30, 'min_score' => null, 'sort_order' => 3],
            ],
            'criteria' => [
                [
                    'code' => 'has_books',
                    'label_ar' => 'هل تحتفظ المنشأة بسجلات دخل ومصروف؟',
                    'description_ar' => 'مثال لمعيار نعم/لا إلزامي.',
                    'weight' => 2,
                    'is_mandatory' => true,
                    'missing_data_treatment' => 'counts_as_unmet',
                    'type' => ReadinessCriterion::TYPE_BOOLEAN,
                    'config' => null,
                    'pillar_code' => 'finance',
                    'compliance_requirement_code' => null,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'margin_pct',
                    'label_ar' => 'هامش الربح التقريبي',
                    'description_ar' => 'مثال لشرائح رقمية. اترك الوحدة فارغة إن لم تلزم.',
                    'weight' => 2,
                    'is_mandatory' => false,
                    'missing_data_treatment' => 'excluded',
                    'type' => ReadinessCriterion::TYPE_NUMERIC_BANDS,
                    'config' => [
                        'unit' => '%',
                        'bands' => [
                            ['min' => null, 'max' => 5, 'points' => 0.5],
                            ['min' => 5.01, 'max' => 15, 'points' => 1.5],
                            ['min' => 15.01, 'max' => null, 'points' => 2],
                        ],
                    ],
                    'pillar_code' => 'finance',
                    'compliance_requirement_code' => null,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'license_status',
                    'label_ar' => 'حالة الترخيص',
                    'description_ar' => 'مثال لاختيار من قائمة.',
                    'weight' => 3,
                    'is_mandatory' => true,
                    'missing_data_treatment' => 'needs_review',
                    'type' => ReadinessCriterion::TYPE_CHOICE,
                    'config' => [
                        'options' => [
                            ['value' => 'none', 'label_ar' => 'لا يوجد', 'points' => 0],
                            ['value' => 'in_progress', 'label_ar' => 'قيد الإصدار', 'points' => 1.5],
                            ['value' => 'active', 'label_ar' => 'ساري', 'points' => 3],
                        ],
                    ],
                    'pillar_code' => 'compliance',
                    'compliance_requirement_code' => null,
                    'sort_order' => 3,
                ],
                [
                    'code' => 'receipt_coverage',
                    'label_ar' => 'نسبة الفواتير الموثّقة من المعاملات',
                    'description_ar' => 'مثال لنسبة مستهدفة (higher = الأفضل).',
                    'weight' => 2,
                    'is_mandatory' => false,
                    'missing_data_treatment' => 'excluded',
                    'type' => ReadinessCriterion::TYPE_TARGET_RATIO,
                    'config' => ['target' => 0.8, 'direction' => 'higher', 'cap' => 1],
                    'pillar_code' => 'finance',
                    'compliance_requirement_code' => null,
                    'sort_order' => 4,
                ],
                [
                    'code' => 'advisor_rating',
                    'label_ar' => 'تقييم المستشار لقدرة الإدارة',
                    'description_ar' => 'مثال لمقياس يدوي من 1 إلى 5.',
                    'weight' => 1,
                    'is_mandatory' => false,
                    'missing_data_treatment' => 'needs_review',
                    'type' => ReadinessCriterion::TYPE_MANUAL_SCALE,
                    'config' => ['min' => 1, 'max' => 5, 'labels_ar' => ['ضعيف', 'متوسط', 'جيد', 'قوي', 'ممتاز']],
                    'pillar_code' => 'operations',
                    'compliance_requirement_code' => null,
                    'sort_order' => 5,
                ],
            ],
            'bands' => [
                ['label_ar' => 'يحتاج دعماً', 'min' => 0, 'max' => 39.99, 'color_token' => 'danger', 'sort_order' => 1],
                ['label_ar' => 'قيد التحسين', 'min' => 40, 'max' => 69.99, 'color_token' => 'warning', 'sort_order' => 2],
                ['label_ar' => 'جاهز للمتابعة', 'min' => 70, 'max' => 100, 'color_token' => 'success', 'sort_order' => 3],
            ],
        ];
    }

    public static function excelBytes(): string
    {
        $def = self::definition();

        return SimpleXlsxWriter::build([
            'تعليمات' => self::instructionsSheet(),
            'المحور' => self::pillarsSheet($def['pillars']),
            'المعايير' => self::criteriaSheet($def['criteria']),
            'الشرائح' => self::bandsSheet($def['bands']),
            'البيانات' => [
                ['الحقل', 'القيمة'],
                ['name_ar', $def['name_ar']],
                ['change_summary', $def['change_summary']],
            ],
        ]);
    }

    public static function blankExcelBytes(): string
    {
        return SimpleXlsxWriter::build([
            'تعليمات' => self::instructionsSheet(),
            'المحور' => array_merge([self::pillarHeaders()], [array_fill(0, 5, '')]),
            'المعايير' => array_merge([self::criterionHeaders()], [array_fill(0, 11, '')]),
            'الشرائح' => array_merge([self::bandHeaders()], [
                ['يحتاج دعماً', 0, 39.99, 'danger', 1],
                ['قيد التحسين', 40, 69.99, 'warning', 2],
                ['جاهز للمتابعة', 70, 100, 'success', 3],
            ]),
            'البيانات' => [
                ['الحقل', 'القيمة'],
                ['name_ar', 'قواعد جاهزية جديدة'],
                ['change_summary', ''],
            ],
        ]);
    }

    /** Build a portable definition from an uploaded Excel workbook produced by this template. */
    public static function definitionFromExcel(string $path): array
    {
        $sheets = self::readSheets($path);
        $meta = self::metaFromSheet($sheets['البيانات'] ?? $sheets['meta'] ?? []);
        $pillars = self::rowsToAssoc($sheets['المحور'] ?? $sheets['pillars'] ?? [], self::pillarHeaders());
        $criteria = self::rowsToAssoc($sheets['المعايير'] ?? $sheets['criteria'] ?? [], self::criterionHeaders());
        $bands = self::rowsToAssoc($sheets['الشرائح'] ?? $sheets['bands'] ?? [], self::bandHeaders());

        $definition = [
            'format' => ReadinessRuleSetValidator::FORMAT,
            'format_version' => ReadinessRuleSetValidator::FORMAT_VERSION,
            'name_ar' => $meta['name_ar'] ?? 'قواعد مستوردة من Excel',
            'change_summary' => ($meta['change_summary'] ?? '') !== '' ? $meta['change_summary'] : null,
            'source_version_number' => null,
            'pillars' => [],
            'criteria' => [],
            'bands' => [],
        ];

        foreach ($pillars as $i => $row) {
            if (trim((string) ($row['code'] ?? '')) === '') {
                continue;
            }
            $definition['pillars'][] = [
                'code' => (string) $row['code'],
                'label_ar' => (string) ($row['label_ar'] ?? ''),
                'weight' => self::nullableNumber($row['weight'] ?? null),
                'min_score' => self::nullableNumber($row['min_score'] ?? null),
                'sort_order' => (int) ($row['sort_order'] ?? $i + 1),
            ];
        }

        foreach ($criteria as $i => $row) {
            if (trim((string) ($row['code'] ?? '')) === '') {
                continue;
            }
            $configRaw = trim((string) ($row['config_json'] ?? ''));
            $config = $configRaw === '' ? null : json_decode($configRaw, true);
            if ($configRaw !== '' && ! is_array($config) && $config !== null) {
                throw new InvalidArgumentException('عمود config_json في صف المعيار '.($i + 2).' ليس JSON صالحاً.');
            }
            $type = (string) ($row['type'] ?? ReadinessCriterion::TYPE_BOOLEAN);
            $definition['criteria'][] = [
                'code' => (string) $row['code'],
                'label_ar' => (string) ($row['label_ar'] ?? ''),
                'description_ar' => (($row['description_ar'] ?? '') !== '' ? (string) $row['description_ar'] : null),
                'weight' => self::nullableNumber($row['weight'] ?? null) ?? 0,
                'is_mandatory' => self::truthy($row['is_mandatory'] ?? false),
                'missing_data_treatment' => (string) ($row['missing_data_treatment'] ?? 'counts_as_unmet'),
                'type' => $type,
                'config' => $type === ReadinessCriterion::TYPE_BOOLEAN ? null : $config,
                'pillar_code' => (($row['pillar_code'] ?? '') !== '' ? (string) $row['pillar_code'] : null),
                'compliance_requirement_code' => (($row['compliance_requirement_code'] ?? '') !== '' ? (string) $row['compliance_requirement_code'] : null),
                'sort_order' => (int) ($row['sort_order'] ?? $i + 1),
            ];
        }

        foreach ($bands as $i => $row) {
            if (trim((string) ($row['label_ar'] ?? '')) === '') {
                continue;
            }
            $definition['bands'][] = [
                'label_ar' => (string) $row['label_ar'],
                'min' => (float) ($row['min'] ?? 0),
                'max' => (float) ($row['max'] ?? 0),
                'color_token' => (string) ($row['color_token'] ?? 'muted'),
                'sort_order' => (int) ($row['sort_order'] ?? $i + 1),
            ];
        }

        return $definition;
    }

    /** @return list<list<string|int|float|null>> */
    private static function instructionsSheet(): array
    {
        return [
            ['الموضوع', 'التوضيح'],
            ['الغرض', 'هذا الملف يساعد فريق بيكون على إعداد قواعد الجاهزية خارج المنصة ثم استيرادها.'],
            ['الاستيراد', 'بعد التعبئة: ارفع الملف من صفحة قواعد الجاهزية (استيراد Excel)، أو استخدم ملف JSON المثال الجاهز.'],
            ['أوراق العمل', 'المحور = محاور التقييم. المعايير = الأسئلة. الشرائح = تفسير النتيجة 0–100. البيانات = اسم القواعد.'],
            ['أنواع المعايير', 'boolean | numeric_bands | choice | target_ratio | manual_scale'],
            ['config_json', 'اكتب إعداد النوع كـ JSON في العمود. لمعيار boolean اتركه فارغاً.'],
            ['مثال boolean', 'اترك config_json فارغاً'],
            ['مثال choice', '{"options":[{"value":"a","label_ar":"أ","points":1},{"value":"b","label_ar":"ب","points":2}]}'],
            ['مثال numeric_bands', '{"unit":"%","bands":[{"min":null,"max":10,"points":1},{"min":10.01,"max":null,"points":2}]}'],
            ['مثال target_ratio', '{"target":0.8,"direction":"higher","cap":1}'],
            ['مثال manual_scale', '{"min":1,"max":5,"labels_ar":["ضعيف","ممتاز"]}'],
            ['معالجة الناقص', 'counts_as_unmet | excluded | needs_review'],
            ['الألوان', 'primary | success | warning | danger | muted'],
            ['تنبيه', 'المثال تعليمي فقط. لا تعتمد أوزاناً أو أسئلة قبل موافقة بيكون.'],
        ];
    }

    /** @return list<string> */
    private static function pillarHeaders(): array
    {
        return ['code', 'label_ar', 'weight', 'min_score', 'sort_order'];
    }

    /** @return list<string> */
    private static function criterionHeaders(): array
    {
        return ['code', 'label_ar', 'description_ar', 'weight', 'is_mandatory', 'missing_data_treatment', 'type', 'pillar_code', 'compliance_requirement_code', 'sort_order', 'config_json'];
    }

    /** @return list<string> */
    private static function bandHeaders(): array
    {
        return ['label_ar', 'min', 'max', 'color_token', 'sort_order'];
    }

    /** @param list<array<string,mixed>> $pillars @return list<list<string|int|float|null>> */
    private static function pillarsSheet(array $pillars): array
    {
        $rows = [self::pillarHeaders()];
        foreach ($pillars as $p) {
            $rows[] = [$p['code'], $p['label_ar'], $p['weight'], $p['min_score'], $p['sort_order']];
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $criteria @return list<list<string|int|float|null>> */
    private static function criteriaSheet(array $criteria): array
    {
        $rows = [self::criterionHeaders()];
        foreach ($criteria as $c) {
            $rows[] = [
                $c['code'],
                $c['label_ar'],
                $c['description_ar'],
                $c['weight'],
                $c['is_mandatory'] ? 1 : 0,
                $c['missing_data_treatment'],
                $c['type'],
                $c['pillar_code'],
                $c['compliance_requirement_code'],
                $c['sort_order'],
                $c['config'] === null ? '' : json_encode($c['config'], JSON_UNESCAPED_UNICODE),
            ];
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $bands @return list<list<string|int|float|null>> */
    private static function bandsSheet(array $bands): array
    {
        $rows = [self::bandHeaders()];
        foreach ($bands as $b) {
            $rows[] = [$b['label_ar'], $b['min'], $b['max'], $b['color_token'], $b['sort_order']];
        }

        return $rows;
    }

    /** @return array<string, list<list<string>>> */
    private static function readSheets(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('تعذر فتح ملف Excel.');
        }

        $shared = [];
        $sst = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sst) && preg_match_all('/<si>(.*?)<\/si>/s', $sst, $matches)) {
            foreach ($matches[1] as $si) {
                if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $texts)) {
                    $shared[] = html_entity_decode(implode('', $texts[1]), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                } else {
                    $shared[] = '';
                }
            }
        }

        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (! is_string($workbook) || ! is_string($rels)) {
            $zip->close();
            throw new InvalidArgumentException('ملف Excel غير مكتمل.');
        }

        $idToTarget = [];
        if (preg_match_all('/Id="(rId\d+)"[^>]*Target="([^"]+)"/', $rels, $rm)) {
            foreach ($rm[1] as $i => $id) {
                $idToTarget[$id] = ltrim(str_replace('\\', '/', $rm[2][$i]), '/');
            }
        }

        $sheets = [];
        if (preg_match_all('/<sheet[^>]*name="([^"]+)"[^>]*r:id="(rId\d+)"/', $workbook, $sm)) {
            foreach ($sm[1] as $i => $name) {
                $target = $idToTarget[$sm[2][$i]] ?? null;
                if ($target === null) {
                    continue;
                }
                if (! str_starts_with($target, 'xl/')) {
                    $target = 'xl/'.$target;
                }
                $xml = $zip->getFromName($target);
                if (! is_string($xml)) {
                    continue;
                }
                $sheets[html_entity_decode($name, ENT_XML1 | ENT_QUOTES, 'UTF-8')] = self::sheetRows($xml, $shared);
            }
        }

        $zip->close();

        return $sheets;
    }

    /** @param list<string> $shared @return list<list<string>> */
    private static function sheetRows(string $xml, array $shared): array
    {
        $rows = [];
        if (! preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $xml, $rowMatches)) {
            return [];
        }
        foreach ($rowMatches[1] as $rowXml) {
            $cells = [];
            if (preg_match_all('/<c r="([A-Z]+)(\d+)"([^>]*)>(?:.*?<v>(.*?)<\/v>)?/s', $rowXml, $cm, PREG_SET_ORDER)) {
                foreach ($cm as $cell) {
                    $col = self::colIndex($cell[1]);
                    $attrs = $cell[3];
                    $raw = $cell[4] ?? '';
                    $value = $raw;
                    if (str_contains($attrs, 't="s"') && $raw !== '' && isset($shared[(int) $raw])) {
                        $value = $shared[(int) $raw];
                    } else {
                        $value = html_entity_decode($raw, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    }
                    $cells[$col] = $value;
                }
            }
            if ($cells === []) {
                continue;
            }
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private static function colIndex(string $col): int
    {
        $n = 0;
        foreach (str_split($col) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** @param list<list<string>> $rows @param list<string> $expected @return list<array<string,string>> */
    private static function rowsToAssoc(array $rows, array $expected): array
    {
        if ($rows === []) {
            return [];
        }
        $headers = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $out = [];
        foreach (array_slice($rows, 1) as $row) {
            $assoc = [];
            foreach ($expected as $key) {
                $idx = array_search($key, $headers, true);
                $assoc[$key] = $idx === false ? '' : (string) ($row[$idx] ?? '');
            }
            $out[] = $assoc;
        }

        return $out;
    }

    /** @param list<list<string>> $rows @return array<string,string> */
    private static function metaFromSheet(array $rows): array
    {
        $meta = [];
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }
            $key = trim((string) ($row[0] ?? ''));
            if ($key !== '') {
                $meta[$key] = (string) ($row[1] ?? '');
            }
        }

        return $meta;
    }

    private static function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'y', 'نعم'], true);
    }
}
