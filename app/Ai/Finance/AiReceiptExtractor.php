<?php

namespace App\Ai\Finance;

use App\Ai\Contracts\AiProvider;
use App\Ai\Support\AiDataGuard;
use App\Contracts\ReceiptExtractor;
use App\Models\Document;
use App\Services\Ai\ReceiptExtractionResult;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * AI-backed OCR suggestions (rules §15). Only the receipt file itself is sent:
 * no enterprise profile or user data (beacon-platform §74). Results are ALWAYS
 * needsReview = true; the finance review/confirm flow stays authoritative.
 *
 * TODO: BUSINESS DECISION REQUIRED — a confidence threshold below which a
 * result is called "weak". Until decided every result is marked for review.
 */
class AiReceiptExtractor implements ReceiptExtractor
{
    public function __construct(private readonly AiProvider $provider) {}

    public function name(): string
    {
        return 'ai';
    }

    public function extract(Document $document): ReceiptExtractionResult
    {
        if (! in_array($document->mime_type, Document::PREVIEWABLE_MIME_TYPES, true)) {
            return new ReceiptExtractionResult(failed: true);
        }

        if (! AiDataGuard::allows(AiDataGuard::RECEIPT)) {
            return new ReceiptExtractionResult(failed: true);
        }

        try {
            $contents = Storage::disk($document->disk)->get($document->path);
            if ($contents === null) {
                return new ReceiptExtractionResult(failed: true);
            }
            $r = $this->provider->extractReceipt($contents, $document->mime_type);
        } catch (Throwable $e) {
            report($e);

            return new ReceiptExtractionResult(failed: true);
        }

        $amount = isset($r['amount']) ? $this->normaliseAmount((string) $r['amount']) : null;
        $date = isset($r['transaction_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['transaction_date']) ? $r['transaction_date'] : null;
        $currency = isset($r['currency']) && preg_match('/^[A-Z]{3}$/', (string) $r['currency']) ? $r['currency'] : null;
        $counterparty = isset($r['counterparty']) ? mb_substr(strip_tags((string) $r['counterparty']), 0, 150) : null;
        $invoice = isset($r['invoice_number']) ? trim(strip_tags((string) $r['invoice_number'])) : '';
        $items = $this->normaliseItems($r['items'] ?? []);

        // Lightweight text for category/type suggestion — never persisted.
        $rawBits = array_filter([
            $counterparty,
            $invoice !== '' ? 'فاتورة '.$invoice : null,
            ...array_map(fn (array $i) => $i['description'] ?? null, $items),
        ], fn ($v) => is_string($v) && $v !== '');

        return new ReceiptExtractionResult(
            transactionDate: $date,
            amount: $amount,
            currency: $currency,
            counterparty: $counterparty ?: null,
            confidence: isset($r['confidence']) ? max(0.0, min(1.0, (float) $r['confidence'])) : null,
            needsReview: true,
            rawText: $rawBits !== [] ? implode("\n", $rawBits) : null,
            items: $items,
            invoiceNumber: $invoice !== '' && preg_match('/^[\p{L}\d\-\/]{1,50}$/u', $invoice) ? $invoice : null,
        );
    }

    /** Accept Arabic-Indic and Western numerals (rules §15). */
    private function normaliseAmount(string $raw): ?string
    {
        $raw = strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => '', ',' => '']);

        return preg_match('/^\d+(\.\d{1,2})?$/', $raw) ? $raw : null;
    }

    /**
     * @param  mixed  $items
     * @return list<array{description: ?string, quantity: int|float|null, unit_price: int|float|null, total: int|float|null}>
     */
    private function normaliseItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $num = function (mixed $v): int|float|null {
            if (! is_int($v) && ! is_float($v) && ! (is_string($v) && is_numeric($v))) {
                return null;
            }
            $n = round((float) $v, 2);

            return $n < 0 || $n > 9_999_999_999_999 ? null : ($n == (int) $n ? (int) $n : $n);
        };

        $out = [];
        foreach (array_slice($items, 0, 100) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $description = isset($row['description']) ? trim(strip_tags((string) $row['description'])) : '';
            $item = [
                'description' => $description !== '' ? mb_substr($description, 0, 255) : null,
                'quantity' => $num($row['quantity'] ?? null),
                'unit_price' => $num($row['unit_price'] ?? null),
                'total' => $num($row['total'] ?? null),
            ];
            if ($item['description'] !== null || $item['total'] !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
