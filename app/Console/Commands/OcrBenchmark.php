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
    protected $signature = 'ocr:benchmark {dir=storage/app/ocr-benchmark} {--preprocess=} {--psm=} {--by-variant}';

    protected $description = 'Measure local Tesseract receipt OCR accuracy on synthetic fixtures';

    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        $truth = json_decode((string) @file_get_contents("$dir/truth.json"), true);
        if (! is_array($truth)) {
            $this->error("No truth.json in $dir (run scripts/ocr-benchmark/generate.py first).");

            return self::FAILURE;
        }
        foreach (['preprocess' => 'ai.ocr.preprocess'] as $opt => $key) {
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
            $raw = (string) $ocr->ocrFile("$dir/{$t['file']}");
            $r = $parser->parse($raw);
            $rows[] = [
                'variant' => $t['variant'],
                'amount' => (string) $r->amount === $t['amount'],
                'date' => $r->transactionDate === $t['date'],
                'currency' => $r->currency === $t['currency'],
                'counterparty' => $r->counterparty !== null && trim($r->counterparty) === $t['counterparty'],
                'chars' => $this->charAccuracy($t['text'], $raw),
            ];
        }

        $summ = function (array $rs, string $label): array {
            $n = max(1, count($rs));
            $pct = fn ($k) => round(100 * count(array_filter($rs, fn ($r) => $r[$k])) / $n, 1);

            return [$label, count($rs), $pct('amount'), $pct('date'), $pct('currency'), $pct('counterparty'), round(100 * array_sum(array_column($rs, 'chars')) / $n, 1)];
        };
        $table = [$summ($rows, 'ALL')];
        if ($this->option('by-variant')) {
            foreach (collect($rows)->groupBy('variant') as $v => $rs) {
                $table[] = $summ($rs->all(), $v);
            }
        }
        $this->info(sprintf('preprocess=%s psm=%s', var_export(config('ai.ocr.preprocess'), true), var_export(config('ai.ocr.psm'), true)));
        $this->table(['set', 'n', 'amount %', 'date %', 'currency %', 'counterparty %', 'char acc %'], $table);

        return self::SUCCESS;
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
