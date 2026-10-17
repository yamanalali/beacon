<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FinancialTransaction extends Model
{
    /**
     * Financial fields are set explicitly inside actions only.
     *
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'payment_status' => PaymentStatus::class,
            'status' => TransactionStatus::class,
            'transaction_date' => 'date',
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FinancialTransaction $transaction): void {
            if (blank($transaction->public_id)) {
                $transaction->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopePosted(Builder $query): void
    {
        $query->where('status', TransactionStatus::Posted->value);
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(TransactionSettlement::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id')->orderBy('position')->orderBy('id');
    }

    /**
     * Sum of item totals in minor units (0 when there are no items).
     */
    public function itemsTotalMinor(): int
    {
        return $this->items->sum(fn (TransactionItem $i): int => Money::toMinor((string) $i->total));
    }

    /**
     * Item totals differ from the transaction amount. Warning only, never blocks saving
     * (tax / discount / delivery can legitimately cause a difference).
     */
    public function itemsMismatch(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        $total = '0';
        foreach ($this->items as $item) {
            $total = bcadd($total, (string) ($item->getAttributes()['total'] ?? '0'), 6);
        }

        $diff = bcsub($total, (string) ($this->getAttributes()['amount'] ?? '0'), 6);
        if (bccomp($diff, '0', 6) < 0) {
            $diff = bcmul($diff, '-1', 6);
        }

        return bccomp($diff, self::itemsTolerance((string) $this->currency), 6) > 0;
    }

    /**
     * Allowed |items total - amount|: half the smallest unit of the currency
     * (SYP has no fractional unit in practice → 0.5; others → 0.005).
     */
    public static function itemsTolerance(string $currency): string
    {
        return strtoupper($currency) === 'SYP' ? '0.5' : '0.005';
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function correctedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_transaction_id');
    }

    public function correction(): HasOne
    {
        return $this->hasOne(self::class, 'corrects_transaction_id');
    }

    public function amountMinor(): int
    {
        return Money::toMinor((string) $this->amount);
    }

    public function settledMinor(): int
    {
        return $this->settlements->sum(fn (TransactionSettlement $s): int => Money::toMinor((string) $s->amount));
    }

    public function outstandingMinor(): int
    {
        return $this->amountMinor() - $this->settledMinor();
    }
}
