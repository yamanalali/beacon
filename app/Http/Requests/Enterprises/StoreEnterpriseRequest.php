<?php

namespace App\Http\Requests\Enterprises;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnterpriseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Enterprise::class) ?? false;
    }

    /**
     * Onboarding stores only the fields needed to open an enterprise record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            // TODO: BUSINESS DECISION REQUIRED — allowed locations are the documented programme areas only.
            'location' => ['required', 'string', 'in:damascus,rural_damascus,daraa'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'required_without:contact_email'],
            'contact_email' => ['nullable', 'email', 'max:255', 'required_without:contact_phone'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required' => ui('validation.business_name_required'),
            'location.required' => ui('validation.location_required'),
            'location.in' => ui('validation.location_in'),
            'contact_phone.required_without' => ui('validation.contact_phone_or_email'),
            'contact_email.required_without' => ui('validation.contact_phone_or_email'),
            'contact_email.email' => ui('validation.contact_email_invalid'),
        ];
    }
}
