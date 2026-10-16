<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * Storage location is never sent to the client.
     *
     * @var list<string>
     */
    protected $hidden = ['disk', 'path'];

    public const PREVIEWABLE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if (blank($document->public_id)) {
                $document->public_id = (string) Str::ulid();
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }
}
