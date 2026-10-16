<?php

namespace App\Http\Requests\Readiness;

use App\Support\ReadinessScorer;
use Illuminate\Validation\Rule;

class PillarRequest extends ReadinessAdminRequest
{
    public function rules(): array
    {
        $set = $this->ruleSet();
        $pillar = $this->route('pillar');
        $creating = $pillar === null;

        return [
            'code' => [$creating ? 'required' : 'sometimes', 'string', 'max:64', 'alpha_dash', Rule::notIn([ReadinessScorer::IMPLICIT_PILLAR]),
                Rule::unique('readiness_pillars')->where('readiness_rule_set_id', $set?->id)->ignore($pillar?->id)],
            'label_ar' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'weight' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0', 'max:1000'],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'readiness_rule_set_id' => ['prohibited'],
        ];
    }
}
