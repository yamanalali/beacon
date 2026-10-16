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
