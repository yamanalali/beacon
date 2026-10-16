<?php

namespace App\Http\Requests\Readiness;

use App\Models\ReadinessCriterion;
use App\Support\ReadinessRuleSetValidator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CriterionRequest extends ReadinessAdminRequest
{
    public function rules(): array
    {
        $set = $this->ruleSet();
        $criterion = $this->route('criterion');
        $creating = $criterion === null;
        $req = $creating ? 'required' : 'sometimes';

        return [
            'code' => [$req, 'string', 'max:64', 'alpha_dash', Rule::unique('readiness_criteria')->where('readiness_rule_set_id', $set?->id)->ignore($criterion?->id)],
            'label_ar' => [$req, 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'weight' => [$req, 'numeric', 'min:0', 'max:1000'],
            'is_mandatory' => [$req, 'boolean'],
            'missing_data_treatment' => [$req, Rule::in(array_keys(ReadinessCriterion::MISSING_DATA_TREATMENTS))],
            'compliance_requirement_id' => ['nullable', 'integer', 'exists:compliance_requirements,id'],
            // QA: a pillar from another rule set is rejected.
            'readiness_pillar_id' => ['nullable', 'integer', Rule::exists('readiness_pillars', 'id')->where('readiness_rule_set_id', $set?->id)],
            'type' => ['sometimes', Rule::in(ReadinessCriterion::TYPES)],
            'config' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'readiness_rule_set_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('type')) {
                return;
            }
            $criterion = $this->route('criterion');
            $type = $this->input('type', $criterion?->type ?? ReadinessCriterion::TYPE_BOOLEAN);
            $config = $this->exists('config') ? $this->input('config') : ($this->exists('type') ? null : $criterion?->config);
            foreach (ReadinessRuleSetValidator::configErrors($type, $config) as $message) {
                $validator->errors()->add('config', $message);
            }
        }];
    }
}
