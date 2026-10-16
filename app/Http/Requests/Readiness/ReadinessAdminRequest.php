<?php

namespace App\Http\Requests\Readiness;

use App\Models\ReadinessRuleSet;
use App\Support\ReadinessAccess;
use Illuminate\Foundation\Http\FormRequest;

/** Base for readiness rule-set admin requests: BEACON_ADMIN only, Arabic messages. */
abstract class ReadinessAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ReadinessAccess::isAdmin($this->user());
    }

    /** The rule set the request acts on (from {ruleSet}, {pillar}, {criterion} or {band}). */
    public function ruleSet(): ?ReadinessRuleSet
    {
        $set = $this->route('ruleSet');
        if ($set instanceof ReadinessRuleSet) {
            return $set;
        }
        foreach (['pillar', 'criterion', 'band'] as $param) {
            $child = $this->route($param);
            if (is_object($child) && isset($child->readiness_rule_set_id)) {
                return ReadinessRuleSet::query()->find($child->readiness_rule_set_id);
            }
        }

        return null;
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'boolean' => 'حقل :attribute يجب أن يكون نعم أو لا.',
            'array' => 'حقل :attribute يجب أن يكون قائمة.',
            'distinct' => 'حقل :attribute مكرر.',
            'min' => 'حقل :attribute أصغر من المسموح.',
            'max' => 'حقل :attribute أكبر من المسموح.',
            'alpha_dash' => 'الرمز يقبل الحروف اللاتينية والأرقام و - و _ فقط.',
            'unique' => 'هذا الرمز مستخدم في نفس القواعد.',
            'in' => 'قيمة :attribute غير مقبولة.',
            'not_in' => 'قيمة :attribute محجوزة.',
            'exists' => 'العنصر المحدد غير موجود في هذه القواعد.',
            'lte' => 'الحد الأدنى يجب ألا يتجاوز الحد الأعلى.',
            'prohibited' => 'لا يُقبل حقل :attribute هنا.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'الرمز', 'label_ar' => 'الاسم', 'weight' => 'الوزن', 'min_score' => 'الحد الأدنى',
            'sort_order' => 'الترتيب', 'type' => 'النوع', 'config' => 'الإعدادات', 'min' => 'من', 'max' => 'إلى',
            'color_token' => 'اللون', 'ids' => 'الترتيب', 'answers' => 'الإجابات', 'definition' => 'التعريف',
            'readiness_pillar_id' => 'المحور', 'missing_data_treatment' => 'معالجة البيانات الناقصة',
        ];
    }
}
