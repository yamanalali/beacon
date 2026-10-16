<?php

namespace App\Http\Requests\Bookkeeping;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller/action with TransactionPolicy.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type');
        $categories = array_keys(config('bookkeeping.categories.'.$type, []));

        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'currency' => ['required', Rule::in(array_keys(config('bookkeeping.currencies')))],
            'category' => ['required', Rule::in($categories)],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'paid_amount' => ['nullable', 'required_if:payment_status,partially_paid', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'counterparty' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'اختر نوع العملية.',
            'transaction_date.required' => 'تاريخ العملية مطلوب.',
            'transaction_date.before_or_equal' => 'لا يمكن أن يكون التاريخ في المستقبل.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.regex' => 'اكتب المبلغ بالأرقام فقط، مع خانتين عشريتين كحد أقصى.',
            'currency.required' => 'اختر العملة.',
            'currency.in' => 'العملة غير مدعومة.',
            'category.required' => 'اختر التصنيف.',
            'category.in' => 'التصنيف غير مناسب لنوع العملية.',
            'payment_status.required' => 'اختر حالة الدفع.',
            'paid_amount.required_if' => 'اكتب المبلغ المدفوع.',
            'paid_amount.regex' => 'اكتب المبلغ المدفوع بالأرقام فقط.',
        ];
    }
}
