<?php

namespace App\Support;

use App\Models\ComplianceRequirement;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessRuleSet;

/**
 * One set of rules used before publishing, on JSON import and before a preview (QA requirement):
 * weights > 0, valid config per criterion type, non-overlapping numeric bands, score bands covering 0..100
 * without gaps or overlaps, and no ids taken from another rule set (definitions reference pillars by code only).
 */
class ReadinessRuleSetValidator
{
    public const FORMAT = 'beacon.readiness.rule_set';
    public const FORMAT_VERSION = 1;

    /** Keys that would carry a database id from another rule set. */
    private const FORBIDDEN_ID_KEYS = ['id', 'readiness_rule_set_id', 'rule_set_id', 'readiness_pillar_id', 'pillar_id', 'readiness_criterion_id', 'criterion_id'];

    /** Portable definition of a rule set (used by export, duplicate and validation). */
    public static function definition(ReadinessRuleSet $set): array
    {
        $set->loadMissing('pillars', 'criteria.pillar', 'criteria.complianceRequirement', 'bands');

        return [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'name_ar' => $set->name_ar,
            'change_summary' => $set->change_summary,
            'source_version_number' => $set->version_number,
            'pillars' => $set->pillars->map(fn ($p) => [
                'code' => $p->code, 'label_ar' => $p->label_ar, 'weight' => $p->weight === null ? null : (float) $p->weight,
                'min_score' => $p->min_score !== null ? (float) $p->min_score : null, 'sort_order' => (int) $p->sort_order,
            ])->values()->all(),
            'criteria' => $set->criteria->sortBy([['sort_order', 'asc'], ['id', 'asc']])->map(fn ($c) => [
                'code' => $c->code, 'label_ar' => $c->label_ar, 'description_ar' => $c->description_ar,
                'weight' => (float) $c->weight, 'is_mandatory' => (bool) $c->is_mandatory,
                'missing_data_treatment' => $c->missing_data_treatment, 'type' => $c->type ?: ReadinessCriterion::TYPE_BOOLEAN,
                'config' => $c->config, 'pillar_code' => $c->pillar?->code, 'sort_order' => (int) $c->sort_order,
                'compliance_requirement_code' => $c->complianceRequirement?->code,
            ])->values()->all(),
            'bands' => $set->bands->map(fn ($b) => [
                'label_ar' => $b->label_ar, 'min' => (float) $b->min, 'max' => (float) $b->max,
                'color_token' => $b->color_token, 'sort_order' => (int) $b->sort_order,
            ])->values()->all(),
        ];
    }

    /** @return array<string, list<string>> errors keyed by field path (empty = valid) */
    public static function errors(array $def, bool $requireCriteria = true, bool $forPublish = false): array
    {
        $errors = [];
        $add = function (string $key, string $message) use (&$errors): void {
            $errors[$key][] = $message;
        };

        self::rejectIds($def, '', $add);

        $pillarCodes = [];
        foreach (array_values($def['pillars'] ?? []) as $i => $p) {
            $code = $p['code'] ?? null;
            if (! is_string($code) || $code === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $code) || $code === ReadinessScorer::IMPLICIT_PILLAR) {
                $add("pillars.$i.code", 'رمز المحور غير صالح.');
            } elseif (in_array($code, $pillarCodes, true)) {
                $add("pillars.$i.code", 'رمز المحور مكرر.');
            } else {
                $pillarCodes[] = $code;
            }
            if (! is_string($p['label_ar'] ?? null) || trim($p['label_ar']) === '') {
                $add("pillars.$i.label_ar", 'اسم المحور مطلوب.');
            }
            if (($p['weight'] ?? null) !== null && (! is_numeric($p['weight']) || (float) $p['weight'] < 0)) {
                $add("pillars.$i.weight", 'وزن المحور لا يمكن أن يكون سالباً.'); // 0/empty = equal default weight
            }
            $min = $p['min_score'] ?? null;
            if ($min !== null && (! is_numeric($min) || $min < 0 || $min > 100)) {
                $add("pillars.$i.min_score", 'الحد الأدنى للمحور يجب أن يكون بين 0 و100.');
            }
        }

        $criteria = array_values($def['criteria'] ?? []);
        if ($requireCriteria && $criteria === []) {
            $add('criteria', 'لا يمكن نشر قواعد بدون معايير.');
        }
        $codes = [];
        foreach ($criteria as $i => $c) {
            $code = $c['code'] ?? null;
            if (! is_string($code) || $code === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
                $add("criteria.$i.code", 'رمز المعيار غير صالح.');
            } elseif (in_array($code, $codes, true)) {
                $add("criteria.$i.code", 'رمز المعيار مكرر.');
            } else {
                $codes[] = $code;
            }
            if (! is_string($c['label_ar'] ?? null) || trim($c['label_ar']) === '') {
                $add("criteria.$i.label_ar", 'اسم المعيار مطلوب.');
            }
            if (! is_numeric($c['weight'] ?? null) || (float) $c['weight'] <= 0) {
                $add("criteria.$i.weight", 'وزن المعيار يجب أن يكون أكبر من صفر.');
            }
            if (! array_key_exists($c['missing_data_treatment'] ?? '', ReadinessCriterion::MISSING_DATA_TREATMENTS)) {
                $add("criteria.$i.missing_data_treatment", 'طريقة معالجة البيانات الناقصة غير صالحة.');
            }
            $pillarCode = $c['pillar_code'] ?? null;
            if ($pillarCode !== null && ! in_array($pillarCode, $pillarCodes, true)) {
                $add("criteria.$i.pillar_code", 'المحور المحدد غير موجود في هذه القواعد.');
            }
            foreach (self::configErrors($c['type'] ?? ReadinessCriterion::TYPE_BOOLEAN, $c['config'] ?? null) as $message) {
                $add("criteria.$i.config", $message);
            }
        }

        // Decision: publishing without score bands is forbidden; preview and import (creates a draft) still allow it.
        if ($forPublish && array_values($def['bands'] ?? []) === []) {
            $add('bands', 'لا يمكن نشر القواعد بدون نطاقات تصنيف.');
        }

        foreach (self::bandErrors(array_values($def['bands'] ?? [])) as $key => $messages) {
            foreach ($messages as $message) {
                $add($key, $message);
            }
        }

        return $errors;
    }

    /** @return list<string> */
    public static function configErrors(mixed $type, mixed $config): array
    {
        if (! in_array($type, ReadinessCriterion::TYPES, true)) {
            return ['نوع المعيار غير صالح.'];
        }
        if ($type === ReadinessCriterion::TYPE_BOOLEAN) {
            return ($config === null || $config === []) ? [] : ['معيار نعم/لا لا يحتاج إعدادات.'];
        }
        if (! is_array($config)) {
            return ['إعدادات المعيار مطلوبة لهذا النوع.'];
        }

        $errors = [];
        switch ($type) {
            case ReadinessCriterion::TYPE_NUMERIC_BANDS:
                if (isset($config['unit']) && ! is_string($config['unit'])) {
                    $errors[] = 'وحدة القياس غير صالحة.';
                }
                $bands = $config['bands'] ?? null;
                if (! is_array($bands) || $bands === []) {
                    return array_merge($errors, ['يجب تحديد شريحة واحدة على الأقل.']);
                }
                $positive = false;
                foreach ($bands as $b) {
                    $min = $b['min'] ?? null;
                    $max = $b['max'] ?? null;
                    if (($min !== null && ! is_numeric($min)) || ($max !== null && ! is_numeric($max)) || ! is_numeric($b['points'] ?? null) || $b['points'] < 0) {
                        return array_merge($errors, ['كل شريحة تحتاج حدوداً رقمية ونقاطاً غير سالبة.']);
                    }
                    if ($min !== null && $max !== null && (float) $min > (float) $max) {
                        $errors[] = 'الحد الأدنى للشريحة أكبر من الحد الأعلى.';
                    }
                    $positive = $positive || $b['points'] > 0;
                }
                if (! $positive) {
                    $errors[] = 'يجب أن تحمل شريحة واحدة على الأقل نقاطاً أكبر من صفر.';
                }
                usort($bands, fn ($a, $b) => ($a['min'] ?? -INF) <=> ($b['min'] ?? -INF));
                for ($i = 1; $i < count($bands); $i++) {
                    $prevMax = $bands[$i - 1]['max'] ?? null;
                    $min = $bands[$i]['min'] ?? null;
                    if ($prevMax === null || $min === null || (float) $min <= (float) $prevMax) {
                        $errors[] = 'الشرائح الرقمية متداخلة.';
                        break;
                    }
                }
                break;

            case ReadinessCriterion::TYPE_CHOICE:
                $options = $config['options'] ?? null;
                if (! is_array($options) || count($options) < 2) {
                    return ['يجب تحديد خيارين على الأقل.'];
                }
                $values = [];
                $positive = false;
                foreach ($options as $o) {
                    if (! isset($o['value']) || ! is_scalar($o['value']) || ! is_string($o['label_ar'] ?? null) || ! is_numeric($o['points'] ?? null) || $o['points'] < 0) {
                        return ['كل خيار يحتاج قيمة واسماً ونقاطاً غير سالبة.'];
                    }
                    $values[] = (string) $o['value'];
                    $positive = $positive || $o['points'] > 0;
                }
                if (count(array_unique($values)) !== count($values)) {
                    $errors[] = 'قيم الخيارات مكررة.';
                }
                if (! $positive) {
                    $errors[] = 'يجب أن يحمل خيار واحد على الأقل نقاطاً أكبر من صفر.';
                }
                break;

            case ReadinessCriterion::TYPE_TARGET_RATIO:
                if (! is_numeric($config['target'] ?? null) || $config['target'] <= 0) {
                    $errors[] = 'القيمة المستهدفة يجب أن تكون أكبر من صفر.';
                }
                if (! in_array($config['direction'] ?? null, ['higher', 'lower'], true)) {
                    $errors[] = 'الاتجاه يجب أن يكون higher أو lower.';
                }
                if (isset($config['cap']) && (! is_numeric($config['cap']) || $config['cap'] <= 0 || $config['cap'] > 1)) {
                    $errors[] = 'سقف النسبة يجب أن يكون بين 0 و1.';
                }
                break;

            case ReadinessCriterion::TYPE_MANUAL_SCALE:
                if (! is_numeric($config['min'] ?? null) || ! is_numeric($config['max'] ?? null) || (float) $config['min'] >= (float) $config['max']) {
                    $errors[] = 'حدود المقياس غير صالحة (الحد الأدنى أصغر من الأعلى).';
                }
                if (isset($config['labels_ar']) && ! is_array($config['labels_ar'])) {
                    $errors[] = 'تسميات المقياس غير صالحة.';
                }
                break;
        }

        return $errors;
    }

    /**
     * Score bands are entered by the admin and are required to publish (see errors($forPublish)); when present they must
     * cover 0..100 with no gap larger than the score resolution (0.01) and no overlap.
     *
     * @return array<string, list<string>>
     */
    public static function bandErrors(array $bands): array
    {
        if ($bands === []) {
            return [];
        }
        $errors = [];
        foreach ($bands as $i => $b) {
            if (! is_string($b['label_ar'] ?? null) || trim($b['label_ar']) === '') {
                $errors["bands.$i.label_ar"][] = 'اسم النطاق مطلوب.';
            }
            if (! is_string($b['color_token'] ?? null) || $b['color_token'] === '') {
                $errors["bands.$i.color_token"][] = 'لون النطاق مطلوب.';
            }
            if (! is_numeric($b['min'] ?? null) || ! is_numeric($b['max'] ?? null) || $b['min'] < 0 || $b['max'] > 100 || $b['min'] > $b['max']) {
                $errors["bands.$i"][] = 'حدود النطاق يجب أن تكون بين 0 و100 والحد الأدنى لا يتجاوز الأعلى.';
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        usort($bands, fn ($a, $b) => (float) $a['min'] <=> (float) $b['min']);
        if ((float) $bands[0]['min'] !== 0.0 || (float) end($bands)['max'] !== 100.0) {
            $errors['bands'][] = 'النطاقات يجب أن تغطي من 0 إلى 100.';
        }
        for ($i = 1; $i < count($bands); $i++) {
            $prev = (float) $bands[$i - 1]['max'];
            $min = (float) $bands[$i]['min'];
            if ($min < $prev) {
                $errors['bands'][] = 'النطاقات متداخلة.';
                break;
            }
            if (round($min - $prev, 2) > 0.01) {
                $errors['bands'][] = 'يوجد فراغ بين النطاقات.';
                break;
            }
        }

        return $errors;
    }

    /** Resolves exported compliance requirement codes; returns [code => id] and records unknown codes as errors. */
    public static function complianceIds(array $def, array &$errors): array
    {
        $codes = array_values(array_filter(array_map(fn ($c) => $c['compliance_requirement_code'] ?? null, $def['criteria'] ?? [])));
        $map = $codes === [] ? [] : ComplianceRequirement::query()->whereIn('code', $codes)->pluck('id', 'code')->all();
        foreach (array_values($def['criteria'] ?? []) as $i => $c) {
            $code = $c['compliance_requirement_code'] ?? null;
            if ($code !== null && ! isset($map[$code])) {
                $errors["criteria.$i.compliance_requirement_code"][] = 'متطلب الامتثال غير موجود.';
            }
        }

        return $map;
    }

    private static function rejectIds(mixed $node, string $path, callable $add): void
    {
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $key => $child) {
            $here = $path === '' ? (string) $key : $path.'.'.$key;
            if (is_string($key) && in_array($key, self::FORBIDDEN_ID_KEYS, true) && ! str_ends_with($path, 'config')) {
                $add($here, 'لا تُقبل المعرّفات في التعريف؛ استخدم الرموز (مثل pillar_code).');

                continue;
            }
            if (is_array($child) && $key !== 'config') {
                self::rejectIds($child, $here, $add);
            }
        }
    }
}
