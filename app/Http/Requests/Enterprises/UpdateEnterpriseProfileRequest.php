<?php

namespace App\Http\Requests\Enterprises;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnterpriseProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enterprise = $this->route('enterprise');

        return $enterprise !== null && ($this->user()?->can('update', $enterprise) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'in:damascus,rural_damascus,daraa'],
            'founding_date' => ['nullable', 'date'],
            'employee_count' => ['nullable', 'integer', 'min:0'],
            'female_employee_count' => ['nullable', 'integer', 'min:0'],
            'male_employee_count' => ['nullable', 'integer', 'min:0'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'required_without:contact_email'],
            'contact_email' => ['nullable', 'email', 'max:255', 'required_without:contact_phone'],
            'registration_status' => ['nullable', 'string', 'max:255'],
            'initial_digital_readiness_note' => ['nullable', 'string'],
            'initial_financial_readiness_note' => ['nullable', 'string'],
            'programme_stage' => ['nullable', 'string', 'max:255'],
            'progress_summary' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'اسم المنشأة مطلوب.',
            'location.required' => 'الموقع مطلوب.',
            'location.in' => 'اختر موقعاً من مناطق البرنامج.',
            'contact_phone.required_without' => 'أدخل رقم الهاتف أو البريد الإلكتروني.',
            'contact_email.required_without' => 'أدخل رقم الهاتف أو البريد الإلكتروني.',
            'contact_email.email' => 'البريد الإلكتروني غير صالح.',
        ];
    }
}
