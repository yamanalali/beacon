<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional invoice line item of a financial transaction.
 * Values are set explicitly inside CreateTransactionAction only.
 */
class TransactionItem extends Model
{
    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }

    /**
     * @return array{id: int, description: string, quantity: float, unit_price: float, total: float}
     */
    public function toProps(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total' => (float) $this->total,
        ];
    }
}
