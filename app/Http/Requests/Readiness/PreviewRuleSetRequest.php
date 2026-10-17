<?php

namespace App\Http\Requests\Readiness;

use App\Models\ReadinessAssessmentResult;
use Illuminate\Validation\Rule;

class PreviewRuleSetRequest extends ReadinessAdminRequest
{
    public function rules(): array
    {
        $ids = $this->ruleSet()?->criteria()->pluck('id')->all() ?? [];

        return [
            'answers' => ['present', 'array'],
            // QA: criteria from another rule set are rejected.
            'answers.*.criterion_id' => ['required', 'integer', 'distinct', Rule::in($ids)],
            'answers.*.outcome' => ['nullable', Rule::in(array_keys(ReadinessAssessmentResult::OUTCOMES))],
            'answers.*.value' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['answers.*.criterion_id.in' => 'المعيار لا ينتمي إلى هذه القواعد.'];
    }
}
