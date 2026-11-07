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
            'required' => ui('validation.attribute.required'),
            'string' => ui('validation.attribute.string'),
            'numeric' => ui('validation.attribute.numeric'),
            'integer' => ui('validation.attribute.integer'),
            'boolean' => ui('validation.attribute.boolean'),
            'array' => ui('validation.attribute.array'),
            'distinct' => ui('validation.attribute.distinct'),
            'min' => ui('validation.attribute.min'),
            'max' => ui('validation.attribute.max'),
            'alpha_dash' => ui('validation.attribute.alpha_dash'),
            'unique' => ui('validation.attribute.unique'),
            'in' => ui('validation.attribute.in'),
            'not_in' => ui('validation.attribute.not_in'),
            'exists' => ui('validation.attribute.exists'),
            'lte' => ui('validation.attribute.lte'),
            'prohibited' => ui('validation.attribute.prohibited'),
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => ui('validation.attr.code'),
            'label_ar' => ui('validation.attr.label_ar'),
            'weight' => ui('validation.attr.weight'),
            'min_score' => ui('validation.attr.min_score'),
            'sort_order' => ui('validation.attr.sort_order'),
            'type' => ui('validation.attr.type'),
            'config' => ui('validation.attr.config'),
            'min' => ui('validation.attr.min'),
            'max' => ui('validation.attr.max'),
            'color_token' => ui('validation.attr.color_token'),
            'ids' => ui('validation.attr.ids'),
            'answers' => ui('validation.attr.answers'),
            'definition' => ui('validation.attr.definition'),
            'readiness_pillar_id' => ui('validation.attr.readiness_pillar_id'),
            'missing_data_treatment' => ui('validation.attr.missing_data_treatment'),
        ];
    }
}
