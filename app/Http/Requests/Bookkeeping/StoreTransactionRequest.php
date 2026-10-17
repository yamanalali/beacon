<?php

namespace App\Http\Requests\Bookkeeping;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use App\Support\Currencies;
use App\Support\TransactionAmountInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['amount', 'paid_amount'] as $field) {
            if ($this->exists($field)) {
                $values[$field] = TransactionAmountInput::normalize($this->input($field));
            }
        }
        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['quantity', 'unit_price', 'total'] as $field) {
                    if (array_key_exists($field, $item)) {
                        $item[$field] = TransactionAmountInput::normalize($item[$field]);
                    }
                }
            }
            unset($item);
            $values['items'] = $items;
        }
        $this->merge($values);
    }

    public function authorize(): bool
    {
        // Authorize before validation so unauthorized users get 403, not field errors.
        // Controllers/actions re-check with TransactionPolicy as well.
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $enterprise = $this->route('enterprise');
        $transaction = $this->route('transaction');
        $extraction = $this->route('extraction');

        return match (true) {
            $enterprise instanceof Enterprise => $user->can('create', [FinancialTransaction::class, $enterprise]),
            $transaction instanceof FinancialTransaction => $user->can('correct', $transaction),
            $extraction instanceof ReceiptExtraction => $user->can('create', [FinancialTransaction::class, $extraction->enterprise]),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type');
        $categories = array_keys(config('bookkeeping.categories.'.$type, []));

        return [
            'client_draft_id' => ['nullable', 'uuid'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'currency' => ['required', Rule::in(array_keys(app(Currencies::class)->available($this->route('transaction')?->currency)))],
            'category' => ['required', Rule::in($categories)],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'paid_amount' => ['nullable', 'required_if:payment_status,partially_paid', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'counterparty' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Optional invoice line items (manual entry, correction, or reviewed receipt/voice draft).
            'items' => ['nullable', 'array', 'max:200'],
            'items.*' => ['array'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'items.*.total' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
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
            'amount.regex' => 'اكتب مبلغاً مثل 1500.50 أو ١٥٠٠٫٥٠، بخانتين عشريتين كحد أقصى.',
            'currency.required' => 'اختر العملة.',
            'currency.in' => 'العملة غير مدعومة.',
            'category.required' => 'اختر التصنيف.',
            'category.in' => 'التصنيف غير مناسب لنوع العملية.',
            'payment_status.required' => 'اختر حالة الدفع.',
            'paid_amount.required_if' => 'اكتب المبلغ المدفوع.',
            'paid_amount.regex' => 'اكتب المبلغ المدفوع مثل 500 أو ٥٠٠، بخانتين عشريتين كحد أقصى.',
            'items.array' => 'صيغة البنود غير صحيحة.',
            'items.max' => 'عدد البنود كبير جداً.',
            'items.*.description.required' => 'اكتب وصف البند.',
            'items.*.description.max' => 'وصف البند طويل جداً.',
            'items.*.quantity.required' => 'اكتب كمية البند.',
            'items.*.quantity.numeric' => 'اكتب الكمية بالأرقام.',
            'items.*.quantity.gt' => 'يجب أن تكون الكمية أكبر من صفر.',
            'items.*.unit_price.required' => 'اكتب سعر الوحدة.',
            'items.*.unit_price.numeric' => 'اكتب سعر الوحدة بالأرقام.',
            'items.*.unit_price.min' => 'لا يمكن أن يكون سعر الوحدة سالباً.',
            'items.*.total.numeric' => 'اكتب إجمالي البند بالأرقام.',
            'items.*.total.min' => 'لا يمكن أن يكون إجمالي البند سالباً.',
        ];
    }
}
