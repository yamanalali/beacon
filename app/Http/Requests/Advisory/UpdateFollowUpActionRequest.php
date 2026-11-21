<?php

namespace App\Http\Requests\Advisory;

use App\Actions\Advisory\UpdateFollowUpAction;
use App\Models\FollowUpAction;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFollowUpActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $followUp = $this->route('followUp');

        if ($user === null || ! $followUp instanceof FollowUpAction) {
            return false;
        }

        return app(UpdateFollowUpAction::class)->canManage($user, $followUp->enterprise);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
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
            'description.required' => ui('validation.action_description_required'),
        ];
    }
}
