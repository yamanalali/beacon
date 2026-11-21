<?php

namespace App\Http\Requests\Advisory;

use App\Actions\Advisory\UpdateAdvisoryActivityAction;
use App\Models\AdvisoryActivity;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvisoryActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $activity = $this->route('activity');

        if ($user === null || ! $activity instanceof AdvisoryActivity) {
            return false;
        }

        return app(UpdateAdvisoryActivityAction::class)->canManage($user, $activity->enterprise);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activity_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activity_date.required' => ui('validation.activity_date_required'),
            'activity_date.date' => ui('validation.activity_date_invalid'),
        ];
    }
}
