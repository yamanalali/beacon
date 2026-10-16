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
            'business_name.required' => 'اسم المنشأة مطلوب.',
            'location.required' => 'الموقع مطلوب.',
            'location.in' => 'اختر موقعاً من مناطق البرنامج.',
            'contact_phone.required_without' => 'أدخل رقم الهاتف أو البريد الإلكتروني.',
            'contact_email.required_without' => 'أدخل رقم الهاتف أو البريد الإلكتروني.',
            'contact_email.email' => 'البريد الإلكتروني غير صالح.',
        ];
    }
}
