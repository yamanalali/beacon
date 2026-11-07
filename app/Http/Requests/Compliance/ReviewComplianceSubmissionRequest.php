<?php

namespace App\Http\Requests\Compliance;

use App\Actions\Compliance\ReviewComplianceSubmissionAction;
use App\Models\Enterprise;
use App\Support\ReadinessAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewComplianceSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enterprise = $this->route('enterprise');

        return $enterprise instanceof Enterprise
            && ReadinessAccess::canManageEnterprise($this->user(), $enterprise);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(ReviewComplianceSubmissionAction::DECISIONS)],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => ui('validation.decision_required'),
            'decision.in' => ui('validation.decision_invalid'),
            'review_note.max' => ui('validation.review_note_max'),
        ];
    }
}
