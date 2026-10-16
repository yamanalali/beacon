<?php

namespace App\Ai\Ocr;

/**
 * Extracts invoice LINE ITEMS from raw OCR text as suggestions (manager decision):
 * [{description, quantity, unit_price, total}] with numbers as int/float and any value
 * that is not printed on the line as null (never guessed). Category stays one per
 * transaction, so items carry none.
 *
 * Supported layouts (Arabic or Western digits, thousands separators, either text direction):
 *   "name qty unit total"      e.g. "Pizza 2 7.50 15.00"
 *   "name qty x unit total"    e.g. "شاي 2 x 1,000 2,000"
 *   "qty x name price"         e.g. "2 x Tea 3.00"        (price = line total)
 *   "name price"               e.g. "خبز 4,500"           (qty/unit unknown => null)
 *   "name price" + next line "unit x qty" / "qty x unit" (restaurant invoices)
 */
class ReceiptLineItemParser
{
    /** Lines that are never items: totals, tax, payment, dates, headers. */
    private const SKIP = '/(المبلغ|الإجمالي|الاجمالي|إجمالي|اجمالي|المجموع|الصافي|الباقي|ضريبة|خصم|نقدا|نقداً|كاش|الصندوق|كاشير|التاريخ|تاريخ|الوقت|هاتف|تلفون|موبايل|فاتورة|رقم|العنوان|شارع|\b(?:total|amount|sum|sub\s*-?\s*total|vat|tax|discount|cash|change|card|visa|paid|balance|due|date|time|tel|phone|invoice|receipt|cashier)\b|\bno\.|#)/iu';

    private const NUM = '\d{1,3}(?:,\d{3})+(?:\.\d{1,3})?|\d+(?:\.\d{1,3})?';

    private const X = '[x×X\*]';

    public function __construct(private readonly ReceiptTextParser $text = new ReceiptTextParser) {}

    /** @return list<array{description: string, quantity: int|float|null, unit_price: int|float|null, total: int|float|null}> */
    public function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $clean = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $this->text->normaliseDigits($raw)) ?? '';
        $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l) ?? ''), preg_split('/\R/u', $clean) ?: []), fn ($l) => $l !== ''));
        $currency = '(?:ل\.?\s?س|ليرة(?:\s*سوري[ةا])?|دولار|درهم|د\.\s?إ|SYP|USD|AED|S\.P|\$)';
        $items = [];
        $n = count($lines);
        // The first text line is the shop header (e.g. «بيتزا 1»), never an item.
        $start = 0;
        while ($start < $n && ! preg_match('/\p{L}{2,}/u', $lines[$start])) {
            $start++;
        }
        for ($i = $start + 1; $i < $n; $i++) {
            $line = trim(preg_replace('/'.$currency.'/iu', ' ', $lines[$i]) ?? '');
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($line === '' || preg_match(self::SKIP, $line) || preg_match('/\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4}|\d{1,2}:\d{2}/', $line)) {
                continue;
            }
            $item = $this->parseLine($line);
            if ($item === null) {
                continue;
            }
            // Restaurant style: "name price" then "unit x qty" (or "qty x unit") on the next line.
            if ($item['quantity'] === null && $i + 1 < $n && ($detail = $this->detailLine($lines[$i + 1], $item['total'])) !== null) {
                $item = [...$item, ...$detail];
                $i++;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Warning flag only (never blocks saving): true when the items' totals don't add up
     * to the receipt total; null when it can't be checked (no items / no total / an item
     * total is unreadable).
     */
    public function totalsMismatch(array $items, int|float|string|null $total): ?bool
    {
        if ($items === [] || $total === null || $total === '' || in_array(null, array_column($items, 'total'), true)) {
            return null;
        }
        $sum = array_sum(array_column($items, 'total'));

        return abs($sum - (float) $total) > 0.01;
    }

    /**
     * Rows where quantity, unit price and total were all read but quantity × unit ≠ total.
     * Values stay as read (BEACON-DECISIONS 2026-10-16 «قيم البنود»); the draft only warns.
     */
    public function inconsistentRows(array $items): int
    {
        return count(array_filter($items, fn ($i) => ($i['quantity'] ?? null) !== null && ($i['unit_price'] ?? null) !== null && ($i['total'] ?? null) !== null
            && abs($i['quantity'] * $i['unit_price'] - $i['total']) > 0.01));
    }

    private function parseLine(string $line): ?array
    {
        $N = self::NUM;
        $X = self::X;
        $patterns = [
            // name qty x unit total | name qty unit total
            ['/^(?<d>.*?\p{L}.*?)\s+(?<q>\d{1,4})\s*(?:'.$X.'\s*)?(?<u>'.$N.')\s+(?<t>'.$N.')$/u', true],
            ['/^(?<t>'.$N.')\s+(?<u>'.$N.')\s*(?:'.$X.'\s*)?(?<q>\d{1,4})\s+(?<d>.*?\p{L}.*?)$/u', true], // RTL order
            // qty x name price
            ['/^(?<q>\d{1,4})\s*'.$X.'\s*(?<d>\p{L}.*?)\s+(?<t>'.$N.')$/u', false],
            ['/^(?<t>'.$N.')\s+(?<d>\p{L}.*?)\s*'.$X.'\s*(?<q>\d{1,4})$/u', false],
            // name price
            ['/^(?<d>\p{L}.*?\p{L})\s+(?<t>'.$N.')$/u', false],
            ['/^(?<t>'.$N.')\s+(?<d>\p{L}.*?\p{L})$/u', false],
        ];
        foreach ($patterns as [$re, $full]) {
            if (! preg_match($re, $line, $m)) {
                continue;
            }
            $desc = trim($m['d'], " \t:-–|.");
            if (mb_strlen($desc) < 2 || ! preg_match('/\p{L}{2,}/u', $desc) || preg_match('/^[\p{Latin}]$/u', $desc)) {
                continue;
            }
            $q = isset($m['q']) && $m['q'] !== '' ? $this->num($m['q']) : null;
            $t = $this->num($m['t']);
            $u = $full ? $this->num($m['u']) : ($q === 1 ? $t : null);
            if ($t === null || $t <= 0) {
                return null;
            }

            return ['description' => mb_substr($desc, 0, 150), 'quantity' => $q, 'unit_price' => $u, 'total' => $t];
        }

        return null;
    }

    /** "9.79 x 1" / "x1 9.79" / "1 x 9.79" detail line under a "name price" line. */
    private function detailLine(string $line, int|float|null $total): ?array
    {
        $line = trim(preg_replace('/[\x{200E}\x{200F}]/u', '', $line) ?? '');
        $N = self::NUM;
        $X = self::X;
        if (preg_match('/^(?<a>'.$N.')\s*'.$X.'\s*(?<b>'.$N.')$/u', $line, $m)) {
            [$a, $b] = [$this->num($m['a']), $this->num($m['b'])];
        } elseif (preg_match('/^'.$X.'\s*(?<a>'.$N.')\s+(?<b>'.$N.')$/u', $line, $m) || preg_match('/^(?<b>'.$N.')\s+'.$X.'\s*(?<a>'.$N.')$/u', $line, $m)
            // OCR often drops the "x": accept two bare numbers, still checked against the total below.
            || preg_match('/^(?<a>'.$N.')\s+(?<b>'.$N.')$/u', $line, $m)) {
            [$a, $b] = [$this->num($m['a']), $this->num($m['b'])];
        } else {
            return null;
        }
        foreach ([[$a, $b], [$b, $a]] as [$qty, $unit]) {
            if (is_int($qty) && $qty > 0 && $qty < 10000 && $total !== null && abs($qty * $unit - $total) <= 0.011 * max(1, $total)) {
                return ['quantity' => $qty, 'unit_price' => $unit];
            }
        }

        return null;
    }

    private function num(string $s): int|float|null
    {
        $s = str_replace(',', '', $s);
        if (! is_numeric($s)) {
            return null;
        }

        return str_contains($s, '.') && (float) $s != (int) $s ? round((float) $s, 2) : (int) $s;
    }
}
