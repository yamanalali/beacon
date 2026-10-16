<?php

namespace App\Ai\Ocr;

use Closure;
use GdImage;
use Throwable;

/**
 * Reads ruled-table invoices (e.g. Arabic RTL "م | البيان | الكمية | السعر | القيمة")
 * cell by cell with local Tesseract. PHP GD only (no extra binaries):
 *  1. upscale + grayscale, find ruled lines with row/column ink profiles;
 *  2. map columns from the header row (keywords), falling back to RTL geometry;
 *  3. split the body into text rows, skip empty rows;
 *  4. OCR the description cell as one line (ara+eng, psm 7) and number cells with a
 *     digits whitelist (psm 7), then cross-check quantity × unit price = total;
 *  5. read the invoice total from the «اجمالي الفاتورة» row (not the largest number).
 * Returns null when no table is found, so callers keep the normal page OCR result.
 * Suggestions only: unread values stay null.
 */
class TableInvoiceReader
{
    private const HEADER_KEYS = [
        'index' => '/^(م|#|ت|رقم|no\.?|s\.?n)$/iu',
        'description' => '/(البيان|الوصف|الصنف|المادة|البند|اسم|description|item|product)/iu',
        'quantity' => '/(الكمية|الكميه|العدد|qty|quantity)/iu',
        'unit_price' => '/(السعر|سعر|الافرادي|الإفرادي|price|rate)/iu',
        'total' => '/(القيمة|القيمه|المبلغ|الإجمالي|الاجمالي|المجموع|total|amount)/iu',
    ];

    private const TOTAL_ROW = '/(اجمال|إجمال|الاجمال|المجموع|الصافي|المستحق|total)/iu';

    private const WIDTH = 1600;

    /** @var list<string> */
    private array $temps = [];

    private GdImage $img;

    private int $w;

    private int $h;

    /** @var array<int, string> per-row dark mask ("1" = ink) */
    private array $mask = [];

    /**
     * @param  Closure(string $file, int $psm, string $langs, ?string $whitelist): ?string  $ocr
     */
    public function __construct(private readonly Closure $ocr) {}

    /**
     * @return null|array{items: list<array{description: ?string, quantity: int|float|null, unit_price: int|float|null, total: int|float|null}>, total: int|float|null, columns: array<string, int>, header_text: string}
     */
    public function read(string $path): ?array
    {
        try {
            if (! $this->load($path)) {
                return null;
            }
            $hLines = $this->horizontalRules();
            if (count($hLines) < 2) {
                return null;
            }
            // Gaps between consecutive horizontal rules that contain >= 3 vertical rules.
            $gaps = [];
            for ($i = 0; $i + 1 < count($hLines); $i++) {
                [$top, $bottom] = [$hLines[$i][1] + 1, $hLines[$i + 1][0] - 1];
                if ($bottom - $top < 12) {
                    continue;
                }
                $v = $this->verticalRules($top + 3, $bottom - 3);
                $gaps[] = ['top' => $top, 'bottom' => $bottom, 'v' => $v];
            }
            $headerIdx = null;
            foreach ($gaps as $k => $g) {
                if (count($g['v']) >= 3) {
                    $headerIdx = $k;
                    break;
                }
            }
            if ($headerIdx === null) {
                return null;
            }
            $header = $gaps[$headerIdx];
            $cols = $this->cells($header['v']);           // left -> right
            if (count($cols) < 3) {
                return null;
            }
            // All text rows of the ruled body (header and data rows may share one gap when
            // the header's bottom rule is faint); the first row is the header.
            $all = array_keys($cols);
            $bands = [];
            $after = [];
            foreach (array_slice($gaps, $headerIdx) as $k => $g) {
                if ($k === 0 || ($after === [] && $this->sameColumns($g['v'], $header['v']))) {
                    foreach ($this->textRows($g, $cols, $all) as $band) {
                        $bands[] = $band;
                    }
                } else {
                    $after[] = $g;
                }
            }
            if (count($bands) < 2) {
                return null;
            }
            [$ht, $hb] = array_shift($bands);
            $map = $this->mapColumns(['top' => $ht, 'bottom' => $hb], $cols);
            if (! isset($map['description'], $map['total'])) {
                return null;
            }

            $items = [];
            $total = null;
            foreach ($bands as [$t, $b]) {
                $row = $this->readRow($t, $b, $cols, $map);
                if ($row === null) {
                    continue;
                }
                if ($row['description'] !== null && preg_match(self::TOTAL_ROW, $row['description'])) {
                    $total ??= $row['total'];
                    break;
                }
                $items[] = $row;
            }
            $sum = $items !== [] && ! in_array(null, array_column($items, 'total'), true) ? array_sum(array_column($items, 'total')) : null;
            foreach ($after as $g) {
                $total ??= $this->totalRow($g, $cols, $map, $sum);
            }
            return ['items' => $items, 'total' => $total, 'columns' => $map, 'header_text' => $this->headerText($header['top'])];
        } catch (Throwable) {
            return null;
        } finally {
            foreach ($this->temps as $t) {
                @unlink($t);
            }
            $this->temps = [];
        }
    }

    private function load(string $path): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }
        $src = @imagecreatefromstring((string) @file_get_contents($path));
        if ($src === false) {
            return false;
        }
        imagepalettetotruecolor($src);
        $w0 = imagesx($src);
        if ($w0 < 100 || imagesy($src) < 100) {
            return false;
        }
        $scale = $w0 < self::WIDTH ? self::WIDTH / $w0 : min(1.0, 2400 / $w0);
        $img = $scale !== 1.0 ? (imagescale($src, (int) round($w0 * $scale), -1, IMG_BICUBIC) ?: $src) : $src;
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        $this->img = $img;
        $this->w = imagesx($img);
        $this->h = imagesy($img);
        if ($this->w * $this->h > 12_000_000) {
            return false;
        }
        // Fixed mid-gray threshold: ruled lines and text are dark; light-gray row lines and
        // shaded header/total cells drop out of the geometry.
        for ($y = 0; $y < $this->h; $y++) {
            $row = '';
            for ($x = 0; $x < $this->w; $x++) {
                $row .= (imagecolorat($img, $x, $y) & 255) < 150 ? '1' : '0';
            }
            $this->mask[$y] = $row;
        }

        return true;
    }

    /** @return list<array{0: int, 1: int}> rows (grouped) with ink across > 45% of the width */
    private function horizontalRules(): array
    {
        $rows = [];
        foreach ($this->mask as $y => $r) {
            if (substr_count($r, '1') > 0.45 * $this->w && $this->longestRun($r) > 0.35 * $this->w) {
                $rows[] = $y;
            }
        }

        return $this->group($rows);
    }

    /** @return list<int> x centres of vertical rules spanning [top, bottom] */
    private function verticalRules(int $top, int $bottom): array
    {
        $h = $bottom - $top + 1;
        if ($h < 8) {
            return [];
        }
        $xs = [];
        for ($x = 0; $x < $this->w; $x++) {
            $c = 0;
            for ($y = $top; $y <= $bottom; $y++) {
                $c += $this->mask[$y][$x] === '1' ? 1 : 0;
            }
            if ($c > 0.9 * $h) {
                $xs[] = $x;
            }
        }

        return array_map(fn ($g) => intdiv($g[0] + $g[1], 2), array_filter($this->group($xs), fn ($g) => $g[1] - $g[0] < 0.03 * $this->w));
    }

    private function longestRun(string $r): int
    {
        $best = 0;
        foreach (explode('0', $r) as $run) {
            $best = max($best, strlen($run));
        }

        return $best;
    }

    /** @param list<int> $v @return list<array{0: int, 1: int}> */
    private function cells(array $v): array
    {
        $cells = [];
        for ($i = 0; $i + 1 < count($v); $i++) {
            if ($v[$i + 1] - $v[$i] > 0.03 * $this->w) {
                $cells[] = [$v[$i], $v[$i + 1]];
            }
        }

        return $cells;
    }

    private function sameColumns(array $v, array $headerV): bool
    {
        if (count($v) < count($headerV) - 0) {
            return false;
        }
        foreach ($headerV as $x) {
            $hit = false;
            foreach ($v as $y) {
                $hit = $hit || abs($x - $y) <= 0.01 * $this->w + 3;
            }
            if (! $hit) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, int> role => column index (left -> right) */
    private function mapColumns(array $header, array $cols): array
    {
        $map = [];
        foreach ($cols as $i => [$l, $r]) {
            $text = trim((string) $this->ocrBox($l, $header['top'], $r, $header['bottom'], 7, 'ara+eng'));
            $text = preg_replace('/[^\p{L}#.]/u', '', $text) ?? '';
            foreach (self::HEADER_KEYS as $role => $re) {
                if (! isset($map[$role]) && $text !== '' && preg_match($re, $text)) {
                    $map[$role] = $i;
                    break;
                }
            }
        }
        // RTL geometry fallback: the widest column is the description; numbers sit to its
        // left in reading order quantity, unit price, total (rightmost to leftmost).
        if (! isset($map['description'])) {
            $widths = array_map(fn ($c) => $c[1] - $c[0], $cols);
            $map['description'] = array_keys($widths, max($widths))[0];
        }
        $left = array_values(array_filter(array_keys($cols), fn ($i) => $i < $map['description'] && ! in_array($i, $map, true)));
        rsort($left); // right -> left
        foreach (['quantity', 'unit_price', 'total'] as $role) {
            if (! isset($map[$role]) && $left !== []) {
                $map[$role] = $role === 'total' ? array_pop($left) : array_shift($left);
            }
        }

        return $map;
    }

    /** Text-line bands inside a body gap, using only cell interiors (ruled lines excluded). */
    private function textRows(array $g, array $cols, array $roles): array
    {
        $ink = [];
        for ($y = $g['top']; $y <= $g['bottom']; $y++) {
            $c = 0;
            foreach ($roles as $i) {
                [$l, $r] = $cols[$i];
                $m = (int) (0.06 * ($r - $l)) + 4;
                $c += substr_count(substr($this->mask[$y], $l + $m, max(0, $r - $l - 2 * $m)), '1');
            }
            $ink[] = $c >= 2 ? $g['top'] + count($ink) : null;
        }
        $bands = $this->group(array_values(array_filter($ink, fn ($v) => $v !== null)));
        $bands = array_values(array_filter($bands, fn ($b) => $b[1] - $b[0] >= 6));
        // Join diacritics/dots that sit just above or below a line.
        $out = [];
        foreach ($bands as $b) {
            if ($out !== [] && $b[0] - $out[count($out) - 1][1] < 8) {
                $out[count($out) - 1][1] = $b[1];
            } else {
                $out[] = $b;
            }
        }

        return array_map(fn ($b) => [max($g['top'], $b[0] - 8), min($g['bottom'], $b[1] + 8)], $out);
    }

    private function readRow(int $t, int $b, array $cols, array $map): ?array
    {
        $cell = function (string $role) use ($t, $b, $cols, $map): ?array {
            if (! isset($map[$role])) {
                return null;
            }
            [$l, $r] = $cols[$map[$role]];
            $m = (int) (0.04 * ($r - $l)) + 4;

            return [$l + $m, $r - $m];
        };
        $empty = fn (?array $c) => $c === null || ! $this->hasInk($c[0], $t, $c[1], $b);
        $d = $cell('description');
        $q = $cell('quantity');
        $u = $cell('unit_price');
        $tt = $cell('total');
        if ($empty($d) && $empty($q) && $empty($u) && $empty($tt)) {
            return null; // empty ruled row
        }
        $desc = $empty($d) ? null : $this->text($d[0], $t, $d[1], $b);
        $desc = $desc !== null ? trim(preg_replace('/[|_\[\]{}“”"]+/u', ' ', $desc) ?? '') : null;
        $num = fn (?array $c) => $empty($c) ? null : $this->number((string) $this->ocrBox($c[0], $t, $c[1], $b, 7, 'eng', '0123456789.,'));

        return $this->crossCheck([
            'description' => $desc !== '' ? $desc : null,
            'quantity' => $num($q),
            'unit_price' => $num($u),
            'total' => $num($tt),
        ]);
    }

    /** Fill one missing value from the other two only when the result is exact (qty × unit = total). */
    public function crossCheck(array $row): array
    {
        [$q, $u, $t] = [$row['quantity'], $row['unit_price'], $row['total']];
        if ($q !== null && $u !== null && $t === null) {
            $row['total'] = $this->tidy($q * $u);
        } elseif ($q !== null && $t !== null && $u === null && $q > 0) {
            $row['unit_price'] = $this->tidy($t / $q);
        } elseif ($u !== null && $t !== null && $q === null && $u > 0 && abs($t / $u - round($t / $u)) < 1e-6) {
            $row['quantity'] = (int) round($t / $u);
        }

        return $row;
    }

    /**
     * Title lines above the table (e.g. «فاتورة مبيعات» on a shaded band), one OCR call
     * per text line; used for the transaction-type suggestion.
     */
    private function headerText(int $tableTop): string
    {
        $rows = [];
        for ($y = 0; $y < $tableTop - 2; $y++) {
            $n = substr_count($this->mask[$y], '1');
            if ($n > 3 && $n < 0.45 * $this->w) {
                $rows[] = $y;
            }
        }
        $out = [];
        foreach (array_slice(array_filter($this->group($rows), fn ($b) => $b[1] - $b[0] >= 8), 0, 8) as [$t, $b]) {
            $line = $this->text(0, max(0, $t - 6), $this->w - 1, min($tableTop - 1, $b + 6));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    /** Arabic text cell: small Arabic text reads best around 17 px; retry larger when empty. */
    private function text(int $l, int $t, int $r, int $b): string
    {
        foreach ([[17, 'ara'], [24, 'ara'], [17, 'ara+eng']] as [$px, $lang]) {
            $s = trim(preg_replace('/[\x{200E}\x{200F}]/u', '', preg_replace('/\s+/u', ' ', (string) $this->ocrBox($l, $t, $r, $b, 7, $lang, null, $px)) ?? '') ?? '');
            if (preg_match('/\p{L}{2,}/u', $s)) {
                return $s;
            }
        }

        return '';
    }

    private function totalRow(array $g, array $cols, array $map, int|float|null $itemsSum): int|float|null
    {
        [$l, $r] = $cols[$map['total']];
        $m = (int) (0.04 * ($r - $l)) + 4;
        $label = $cols[$map['total']][0] > $cols[$map['description']][0]
            ? $this->text($cols[0][0] + $m, $g['top'], $l - $m, $g['bottom'])                  // LTR: label left of total
            : $this->text($r + $m, $g['top'], $cols[count($cols) - 1][1] - $m, $g['bottom']);   // RTL: label right of total
        $value = $this->number((string) $this->ocrBox($l + $m, $g['top'], $r - $m, $g['bottom'], 7, 'eng', '0123456789.,'));
        if ($value === null) {
            return null;
        }
        // The «اجمالي الفاتورة» label; if the label itself is unreadable, accept the merged
        // row only when its value equals the items' sum.
        if (preg_match(self::TOTAL_ROW, $label) || ($itemsSum !== null && abs($itemsSum - $value) < 0.01)) {
            return $value;
        }

        return null;
    }

    private function hasInk(int $l, int $t, int $r, int $b): bool
    {
        $c = 0;
        for ($y = $t; $y <= $b; $y++) {
            $c += substr_count(substr($this->mask[$y], $l, max(0, $r - $l)), '1');
        }

        return $c > 12;
    }

    private function number(string $s): int|float|null
    {
        $s = trim(str_replace([' ', "\n"], '', (new ReceiptTextParser)->normaliseDigits($s)), " \t\r\n.,");
        if (! preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/', $s)) {
            return null; // unreadable: never guessed
        }

        return $this->tidy((float) str_replace(',', '', $s));
    }

    private function tidy(float $v): int|float
    {
        $v = round($v, 2);

        return $v == (int) $v ? (int) $v : $v;
    }

    /** Crops a box from the upscaled grayscale page (white border) and OCRs it. */
    private function ocrBox(int $l, int $t, int $r, int $b, int $psm, string $langs, ?string $whitelist = null, int $inkHeight = 24): ?string
    {
        $w = $r - $l;
        $h = $b - $t;
        if ($w < 4 || $h < 4) {
            return null;
        }
        $scale = 1.0;
        if ($psm === 7) {
            // Trim to the ink and scale so the text is ~24 px tall (LSTM sweet spot); light
            // gray row lines and dotted cell borders drop out with the threshold below.
            [$l, $t, $r, $b] = $this->inkBox($l, $t, $r, $b) ?? [$l, $t, $r, $b];
            $w = $r - $l + 1;
            $h = $b - $t + 1;
            $scale = max(0.2, min(2.0, $inkHeight / max(1, $h)));
        }
        $sw = max(1, (int) round($w * $scale));
        $sh = max(1, (int) round($h * $scale));
        $bin = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $v = imagecolorat($this->img, $l + $x, $t + $y) & 255;
                $v = $v > 190 ? 255 : $v; // drop faint row lines / dotted borders, keep anti-aliased text
                imagesetpixel($bin, $x, $y, ($v << 16) | ($v << 8) | $v);
            }
        }
        $src = $scale !== 1.0 ? (imagescale($bin, $sw, $sh, IMG_BICUBIC) ?: $bin) : $bin;
        $pad = max(10, $sh);
        $dst = imagecreatetruecolor($sw + 2 * $pad, $sh + 2 * $pad);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopy($dst, $src, $pad, $pad, 0, 0, $sw, $sh);
        $file = tempnam(sys_get_temp_dir(), 'ocrt_');
        $this->temps[] = $file;
        imagepng($dst, $file);

        return ($this->ocr)($file, $psm, $langs, $whitelist);
    }

    /** Bounding box of the ink inside a box (ignoring thin full-width/height rules). */
    private function inkBox(int $l, int $t, int $r, int $b): ?array
    {
        [$minX, $minY, $maxX, $maxY] = [PHP_INT_MAX, PHP_INT_MAX, -1, -1];
        $bw = $r - $l + 1;
        for ($y = $t; $y <= $b; $y++) {
            $seg = substr($this->mask[$y], $l, $bw);
            if (substr_count($seg, '1') > 0.8 * $bw) {
                continue; // a ruled line crossing the box
            }
            if (($p = strpos($seg, '1')) !== false) {
                $minX = min($minX, $l + $p);
                $maxX = max($maxX, $l + strrpos($seg, '1'));
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        return $maxX < 0 || $maxY - $minY < 4 ? null : [max($l, $minX - 2), max($t, $minY - 2), min($r, $maxX + 2), min($b, $maxY + 2)];
    }

    /** @param list<int> $vals sorted @return list<array{0: int, 1: int}> */
    private function group(array $vals): array
    {
        $out = [];
        foreach ($vals as $v) {
            if ($out !== [] && $v - $out[count($out) - 1][1] <= 2) {
                $out[count($out) - 1][1] = $v;
            } else {
                $out[] = [$v, $v];
            }
        }

        return $out;
    }
}
