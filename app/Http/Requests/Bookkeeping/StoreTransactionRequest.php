<?php

namespace App\Http\Requests\Bookkeeping;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use App\Services\Bookkeeping\TransactionCategoryCatalog;
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
                // Receipts often print qty + line total (no unit price), or tax as a total only.
                // Fill the missing side so validation/storage stay simple without blocking review.
                $item = $this->completeItemTotals($item);
            }
            unset($item);
            $values['items'] = $items;
        }
        $this->merge($values);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function completeItemTotals(array $item): array
    {
        $blank = static fn (mixed $v): bool => $v === null || $v === '';

        $qty = $item['quantity'] ?? null;
        $price = $item['unit_price'] ?? null;
        $total = $item['total'] ?? null;

        if ($blank($total) || ! is_numeric($total)) {
            return $item;
        }

        if ($blank($qty) && $blank($price)) {
            // Tax/fee rows: one line total → treat as qty 1 × price = total.
            $item['quantity'] = '1';
            $item['unit_price'] = (string) $total;

            return $item;
        }

        if ($blank($price) && is_numeric($qty) && (float) $qty > 0) {
            $item['unit_price'] = bcdiv((string) $total, (string) $qty, 2);

            return $item;
        }

        if ($blank($qty) && is_numeric($price) && (float) $price > 0) {
            $item['quantity'] = '1';
        }

        return $item;
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
        // A correction may keep the original (possibly since-deactivated) category of the same type.
        $original = $this->route('transaction');
        $keep = $original instanceof FinancialTransaction && $original->type->value === $type ? $original->category : null;
        $categories = array_keys(app(TransactionCategoryCatalog::class)->forType($type, $keep));

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
            'type.required' => ui('validation.transaction_type_required'),
            'transaction_date.required' => ui('validation.transaction_date_required'),
            'transaction_date.before_or_equal' => ui('validation.date_not_future'),
            'amount.required' => ui('validation.amount_required'),
            'amount.regex' => ui('validation.amount_format_long'),
            'currency.required' => ui('validation.currency_required'),
            'currency.in' => ui('validation.currency_invalid'),
            'category.required' => ui('validation.category_required'),
            'category.in' => ui('validation.category_invalid'),
            'payment_status.required' => ui('validation.payment_status_required'),
            'paid_amount.required_if' => ui('validation.paid_amount_required_if'),
            'paid_amount.regex' => ui('validation.paid_amount_format'),
            'items.array' => ui('validation.items_array'),
            'items.max' => ui('validation.items_max'),
            'items.*.description.required' => ui('validation.item_description_required'),
            'items.*.description.max' => ui('validation.item_description_max'),
            'items.*.quantity.required' => ui('validation.item_quantity_required'),
            'items.*.quantity.numeric' => ui('validation.item_quantity_numeric'),
            'items.*.quantity.gt' => ui('validation.item_quantity_gt'),
            'items.*.unit_price.required' => ui('validation.item_unit_price_required'),
            'items.*.unit_price.numeric' => ui('validation.item_unit_price_numeric'),
            'items.*.unit_price.min' => ui('validation.item_unit_price_min'),
            'items.*.total.numeric' => ui('validation.item_total_numeric'),
            'items.*.total.min' => ui('validation.item_total_min'),
        ];
    }
}
