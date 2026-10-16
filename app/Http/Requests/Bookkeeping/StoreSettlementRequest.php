<?php

namespace App\Http\Requests\Bookkeeping;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settlement_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settlement_date.required' => 'تاريخ الدفعة مطلوب.',
            'settlement_date.before_or_equal' => 'لا يمكن أن يكون التاريخ في المستقبل.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.regex' => 'اكتب المبلغ بالأرقام فقط، مع خانتين عشريتين كحد أقصى.',
        ];
    }
}
