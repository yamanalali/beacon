<?php

namespace App\Ai\Http\Requests;

use App\Ai\Models\AiEscalation;
use Illuminate\Foundation\Http\FormRequest;

class ReplyAiEscalationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $escalation = $this->route('escalation');

        return $escalation instanceof AiEscalation
            && $this->user()?->can('reply', $escalation) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_reply' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'staff_reply.required' => 'اكتب الرد قبل الحفظ.',
            'staff_reply.min' => 'الرد قصير جداً.',
            'staff_reply.max' => 'الرد طويل جداً.',
        ];
    }
}
