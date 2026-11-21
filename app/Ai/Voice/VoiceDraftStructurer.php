<?php

namespace App\Ai\Voice;

use App\Ai\Contracts\AiProvider;
use App\Ai\Support\AiDataGuard;
use App\Enums\TransactionType;
use App\Services\Bookkeeping\TransactionCategoryCatalog;
use Throwable;

/**
 * Prefer ChatGPT structuring of a browser/local transcript; fall back to the
 * heuristic VoiceDraftParser when the provider is off, denied, or fails (§77).
 * Never posts — suggestions only.
 */
class VoiceDraftStructurer
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly VoiceDraftParser $parser,
    ) {}

    /**
     * @return array{
     *     amount:?string,
     *     date:?string,
     *     counterparty:?string,
     *     currency:?string,
     *     note:?string,
     *     normalized:string,
     *     items:list<array{description:string,quantity:float|int,unit_price:float|int,total:float|int}>,
     *     items_mismatch:bool,
     *     amount_warning:?string,
     *     confidence:?float,
     *     transaction_type:?string,
     *     category:?string,
     *     structure_source:string
     * }
     */
    public function structure(string $text): array
    {
        $normalized = $this->parser->normalizeTerms($text);
        $fallback = fn () => $this->fromParser($text, $normalized);

        if (config('ai.driver', 'null') === 'null' || ! AiDataGuard::allows(AiDataGuard::VOICE_STRUCTURE)) {
            return $fallback();
        }

        try {
            $raw = $this->provider->structureVoiceDraft(mb_substr($text, 0, 1000));
        } catch (Throwable $e) {
            report($e);

            return $fallback();
        }

        return $this->fromAi($raw, $normalized);
    }

    /**
     * @return array<string,mixed>
     */
    private function fromParser(string $text, string $normalized): array
    {
        $p = $this->parser->parse($text);
        $p['transaction_type'] = null;
        $p['category'] = null;
        $p['structure_source'] = 'local';

        return $p;
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private function fromAi(array $raw, string $normalized): array
    {
        $amount = isset($raw['amount']) ? trim((string) $raw['amount']) : null;
        if ($amount !== null && ! preg_match('/^\d{1,16}(\.\d{1,2})?$/', $amount)) {
            $amount = null;
        }

        $date = isset($raw['date']) ? trim((string) $raw['date']) : null;
        if ($date !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = null;
        }

        $currency = isset($raw['currency']) ? strtoupper(trim((string) $raw['currency'])) : null;
        if ($currency !== null && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = null;
        }

        $counterparty = isset($raw['counterparty']) ? trim((string) $raw['counterparty']) : null;
        if ($counterparty === '') {
            $counterparty = null;
        } elseif ($counterparty !== null) {
            $counterparty = mb_substr($counterparty, 0, 150);
        }

        $note = isset($raw['note']) ? trim((string) $raw['note']) : null;
        if ($note === '') {
            $note = null;
        } elseif ($note !== null) {
            $note = mb_substr($note, 0, 200);
        }

        $type = isset($raw['transaction_type']) ? trim((string) $raw['transaction_type']) : null;
        $allowedTypes = array_map(fn (TransactionType $t) => $t->value, TransactionType::cases());
        if ($type !== null && ! in_array($type, $allowedTypes, true)) {
            $type = null;
        }

        $category = isset($raw['category']) ? trim((string) $raw['category']) : null;
        if ($category !== null && $type !== null) {
            $allowed = app(TransactionCategoryCatalog::class)->forType($type);
            if (! is_array($allowed) || ! array_key_exists($category, $allowed)) {
                $category = null;
            }
        } elseif ($category !== null && $type === null) {
            $category = null;
        }

        $items = $this->sanitizeItems($raw['items'] ?? []);
        $warning = isset($raw['amount_warning']) ? trim((string) $raw['amount_warning']) : null;
        if ($warning === '') {
            $warning = null;
        }

        $confidence = isset($raw['confidence']) && is_numeric($raw['confidence'])
            ? max(0.0, min(1.0, (float) $raw['confidence']))
            : null;

        return [
            'amount' => $amount,
            'date' => $date,
            'counterparty' => $counterparty,
            'currency' => $currency,
            'note' => $note,
            'normalized' => $normalized,
            'items' => $items,
            'items_mismatch' => (bool) ($raw['items_mismatch'] ?? false),
            'amount_warning' => $warning !== null ? mb_substr($warning, 0, 240) : null,
            'confidence' => $confidence,
            'transaction_type' => $type,
            'category' => $category,
            'structure_source' => 'ai',
        ];
    }

    /**
     * @param  mixed  $items
     * @return list<array{description:string,quantity:float|int,unit_price:float|int,total:float|int}>
     */
    private function sanitizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_slice($items, 0, 20) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $quantity = $row['quantity'] ?? null;
            $unit = $row['unit_price'] ?? null;
            $total = $row['total'] ?? null;
            if (! is_numeric($quantity) || ! is_numeric($unit) || ! is_numeric($total)) {
                continue;
            }
            $out[] = [
                'description' => mb_substr($description, 0, 120),
                'quantity' => 0 + $quantity,
                'unit_price' => 0 + $unit,
                'total' => 0 + $total,
            ];
        }

        return $out;
    }
}
