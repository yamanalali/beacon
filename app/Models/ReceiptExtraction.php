<?php

namespace App\Models;

use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReceiptExtraction extends Model
{
    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => ExtractionStatus::class,
            'suggested_date' => 'date',
            'suggested_amount' => 'decimal:2',
            'confidence' => 'decimal:4',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReceiptExtraction $e): void {
            if (blank($e->public_id)) {
                $e->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function confirmedTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'confirmed_transaction_id');
    }
}
