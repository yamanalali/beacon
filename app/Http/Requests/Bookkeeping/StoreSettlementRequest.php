<?php

namespace App\Http\Requests\Bookkeeping;

use App\Models\FinancialTransaction;
use App\Support\TransactionAmountInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreSettlementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('amount')) {
            $this->merge(['amount' => TransactionAmountInput::normalize($this->input('amount'))]);
        }
    }

    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $this->user() !== null
            && $transaction instanceof FinancialTransaction
            && $this->user()->can('settle', $transaction);
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
            'amount.regex' => 'اكتب المبلغ مثل 500 أو ٥٠٠، بخانتين عشريتين كحد أقصى.',
        ];
    }
}
