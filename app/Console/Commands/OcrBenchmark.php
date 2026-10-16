<?php

namespace App\Console\Commands;

use App\Ai\Ocr\ReceiptTextParser;
use App\Ai\Ocr\TesseractReceiptExtractor;
use Illuminate\Console\Command;

/**
 * Reproducible OCR benchmark on SYNTHETIC receipts (not part of the test suite).
 *
 *   python3 scripts/ocr-benchmark/generate.py storage/app/ocr-benchmark 12
 *   php artisan ocr:benchmark storage/app/ocr-benchmark --preprocess=0
 *   php artisan ocr:benchmark storage/app/ocr-benchmark --preprocess=1 --psm=6
 */
class OcrBenchmark extends Command
{
    protected $signature = 'ocr:benchmark {dir=storage/app/ocr-benchmark} {--preprocess=} {--psm=} {--line-pass=} {--by-variant}';

    protected $description = 'Measure local Tesseract receipt OCR accuracy on synthetic fixtures';

    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        $truth = json_decode((string) @file_get_contents("$dir/truth.json"), true);
        if (! is_array($truth)) {
            $this->error("No truth.json in $dir (run scripts/ocr-benchmark/generate.py first).");

            return self::FAILURE;
        }
        foreach (['preprocess' => 'ai.ocr.preprocess', 'line-pass' => 'ai.ocr.line_pass'] as $opt => $key) {
            if ($this->option($opt) !== null) {
                config([$key => (bool) (int) $this->option($opt)]);
            }
        }
        if ($this->option('psm') !== null) {
            config(['ai.ocr.psm' => $this->option('psm') === '' ? null : (int) $this->option('psm')]);
        }

        $ocr = new TesseractReceiptExtractor($parser = new ReceiptTextParser);
        $rows = [];
        foreach ($truth as $t) {
            $o = $ocr->ocrPageAndLines("$dir/{$t['file']}");
            $raw = (string) $o['page'];
            $r = $parser->parse($raw, $o['lines']);
            [$hit, $exp, $got] = $this->itemScore($t['items'] ?? [], $r->items);
            $rows[] = [
                'variant' => $t['variant'],
                'set' => $t['lang'] ?? 'base',
                'amount' => (string) $r->amount === $t['amount'],
                'date' => $r->transactionDate === $t['date'],
                'currency' => $r->currency === $t['currency'],
                'counterparty' => $r->counterparty !== null && trim($r->counterparty) === $t['counterparty'],
                'chars' => $this->charAccuracy($t['text'], $raw),
                'item_hit' => $hit, 'item_exp' => $exp, 'item_got' => $got,
            ];
        }

        $summ = function (array $rs, string $label): array {
            $n = max(1, count($rs));
            $pct = fn ($k) => round(100 * count(array_filter($rs, fn ($r) => $r[$k])) / $n, 1);

            $hit = array_sum(array_column($rs, 'item_hit'));
            $exp = array_sum(array_column($rs, 'item_exp'));
            $got = array_sum(array_column($rs, 'item_got'));

            return [$label, count($rs), $pct('amount'), $pct('date'), $pct('currency'), $pct('counterparty'), round(100 * array_sum(array_column($rs, 'chars')) / $n, 1),
                $exp ? round(100 * $hit / $exp, 1) : '-', $got ? round(100 * $hit / $got, 1) : '-'];
        };
        $table = [$summ($rows, 'ALL')];
        if ($this->option('by-variant')) {
            foreach (collect($rows)->groupBy('variant') as $v => $rs) {
                $table[] = $summ($rs->all(), $v);
            }
            foreach (collect($rows)->groupBy('set') as $v => $rs) {
                $table[] = $summ($rs->all(), 'set:'.$v);
            }
            $base = collect($rows)->filter(fn ($r) => in_array($r['set'], ['ar', 'en', 'ar-invoice'], true))->all();
            $table[] = $summ($base, 'base set (r*)');
        }
        $this->info(sprintf('preprocess=%s psm=%s', var_export(config('ai.ocr.preprocess'), true), var_export(config('ai.ocr.psm'), true)));
        $this->table(['set', 'n', 'amount %', 'date %', 'currency %', 'counterparty %', 'char acc %', 'item recall %', 'item precision %'], $table);

        return self::SUCCESS;
    }

    /**
     * An item counts as found when its total matches and its description matches after
     * whitespace normalisation (quantity/unit must also match when both are present).
     *
     * @return array{0: int, 1: int, 2: int} [hits, expected, returned]
     */
    private function itemScore(array $expected, array $got): array
    {
        $norm = fn ($s) => preg_replace('/\s+/u', '', (string) $s);
        $hits = 0;
        $pool = $got;
        foreach ($expected as $e) {
            foreach ($pool as $k => $g) {
                $same = fn ($a, $b) => $a === null || $b === null || abs((float) $a - (float) $b) < 0.01;
                if ($norm($g['description']) === $norm($e['description']) && $g['total'] !== null && abs((float) $g['total'] - (float) $e['total']) < 0.01
                    && $same($e['quantity'], $g['quantity']) && $same($e['unit_price'], $g['unit_price'])) {
                    $hits++;
                    unset($pool[$k]);
                    break;
                }
            }
        }

        return [$hits, count($expected), count($got)];
    }

    /** 1 - normalised Levenshtein over characters, whitespace-insensitive. */
    private function charAccuracy(string $expected, string $actual): float
    {
        $norm = fn ($s) => preg_split('//u', preg_replace('/\s+/u', '', (new ReceiptTextParser)->normaliseDigits($s)) ?? '', -1, PREG_SPLIT_NO_EMPTY);
        [$a, $b] = [$norm($expected), $norm($actual)];
        $m = count($a);
        $n = count($b);
        if ($m === 0) {
            return $n === 0 ? 1.0 : 0.0;
        }
        $prev = range(0, $n);
        for ($i = 1; $i <= $m; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $n; $j++) {
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1));
            }
            $prev = $cur;
        }

        return max(0.0, 1 - $prev[$n] / $m);
    }
}
