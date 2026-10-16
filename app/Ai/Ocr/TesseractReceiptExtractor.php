<?php

namespace App\Ai\Ocr;

use App\Contracts\ReceiptExtractor;
use App\Models\Document;
use App\Services\Ai\ReceiptExtractionResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Free, LOCAL receipt OCR using the Tesseract binary (rules §15).
 *
 * Data control (§54): the image is processed on Beacon's own server by a local
 * binary and never leaves it, so this is NOT a third-party processor and does
 * not call DataControl::assertCanShare (manager decision). Any EXTERNAL OCR
 * provider (e.g. AiReceiptExtractor) must still go through AiDataGuard.
 *
 * Results are suggestions only (needsReview = true); the existing
 * review/confirm flow creates the transaction after the user confirms.
 */
class TesseractReceiptExtractor implements ReceiptExtractor
{
    /** Always Arabic + English (manager decision). */
    public const LANGS = 'ara+eng';

    /** @var array<string, list<string>> installed languages per binary+dir */
    private static array $langCache = [];

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/bmp', 'image/gif'];

    public function __construct(private readonly ReceiptTextParser $parser, private ?ImagePreprocessor $preprocessor = null) {}

    public function name(): string
    {
        return 'tesseract';
    }

    public function extract(Document $document): ReceiptExtractionResult
    {
        // PDFs need rasterising first; not supported by this driver yet.
        if (! in_array($document->mime_type, self::IMAGE_TYPES, true)) {
            return new ReceiptExtractionResult(failed: true);
        }

        $tmp = null;
        try {
            $contents = Storage::disk($document->disk)->get($document->path);
            if ($contents === null) {
                return new ReceiptExtractionResult(failed: true);
            }
            $tmp = tempnam(sys_get_temp_dir(), 'ocr_');
            file_put_contents($tmp, $contents);

            $ocr = $this->ocrPageAndLines($tmp);
            $raw = $ocr['page'];
        } catch (Throwable $e) {
            Log::warning('Tesseract OCR unavailable', ['document_id' => $document->id, 'error' => $e->getMessage()]);

            return new ReceiptExtractionResult(failed: true);
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return $raw === null ? new ReceiptExtractionResult(failed: true) : $this->parser->parse($raw, $ocr['lines'], $ocr['table']);
    }

    /**
     * Page OCR plus (when AI_OCR_LINE_PASS is on) a per-line pass (--psm 7 on each text
     * band). Page text stays the source for amount/date/currency; the line text is used for
     * line items and the shop header, which page layout analysis tends to drop.
     *
     * @return array{page: ?string, lines: ?string, table: ?array}
     */
    public function ocrPageAndLines(string $imagePath): array
    {
        if ($this->missingLanguages() !== []) {
            return ['page' => $this->ocrFile($imagePath), 'lines' => null, 'table' => null];
        }
        $pre = $this->preprocessor ??= new ImagePreprocessor;
        try {
            $img = config('ai.ocr.preprocess', false) ? $pre->prepare($imagePath) : $imagePath;
            $page = $this->run($img);
            // Ruled-table invoices are read cell by cell from the preprocessed (upright, deskewed) image.
            $table = $page !== null && config('ai.ocr.table_pass', true) ? $this->tablePass($imagePath, $img) : null;
            $lines = $page !== null && $table === null && config('ai.ocr.line_pass', true) ? $this->linePass($img) : null;

            return ['page' => $page, 'lines' => $lines, 'table' => $table];
        } finally {
            $pre->cleanup();
        }
    }

    /**
     * Ruled-table pass on the original upload and on the preprocessed image (upright,
     * deskewed, contrast-normalised); keeps the more consistent result: rows where
     * quantity × unit price = total, plus a total that equals the items' sum.
     */
    public function tablePass(string $original, string $prepared): ?array
    {
        $reader = fn () => new TableInvoiceReader(fn (string $f, int $psm, string $langs, ?string $wl) => $this->run($f, $psm, $langs, $wl));
        $best = null;
        $bestScore = -1;
        $headers = [];
        foreach (array_unique([$original, $prepared]) as $path) {
            $t = $reader()->read($path);
            if ($t === null || $t['items'] === []) {
                continue;
            }
            $headers[] = $t['header_text'];
            $score = 0;
            foreach ($t['items'] as $i) {
                $score += $i['description'] !== null ? 1 : 0;
                $score += $i['quantity'] !== null && $i['unit_price'] !== null && $i['total'] !== null
                    && abs($i['quantity'] * $i['unit_price'] - $i['total']) < 0.01 ? 3 : 0;
            }
            $totals = array_column($t['items'], 'total');
            if ($t['total'] !== null && ! in_array(null, $totals, true) && abs(array_sum($totals) - $t['total']) < 0.01) {
                $score += 5;
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$t, $score];
            }
        }

        if ($best !== null) {
            // Title lines (e.g. «فاتورة مبيعات») from both passes feed the type suggestion.
            $best['header_text'] = implode("\n", array_unique(array_filter($headers)));
        }

        return $best;
    }

    /** OCR each detected text line on its own; null when the image can't be segmented. */
    public function linePass(string $imagePath): ?string
    {
        $seg = new LineSegmenter;
        $bands = $seg->bands($imagePath);
        if ($bands === [] || count($bands) > (int) config('ai.ocr.line_pass_max_lines', 60)) {
            return null;
        }
        $primary = $seg->crop($imagePath, $bands, 18);
        $files = $primary;
        try {
            $out = [];
            foreach ($primary as $k => $f) {
                $t = $this->run($f, 7);
                if ($t === null) {
                    return null;
                }
                $t = trim(preg_replace('/\s*\R\s*/u', ' ', $t) ?? '');
                if ($t === '') {
                    // Nothing recognised at this size: retry the same band larger once.
                    $retry = $seg->crop($imagePath, [$bands[$k]], 26);
                    $files = [...$files, ...$retry];
                    $t = $retry !== [] ? trim(preg_replace('/\s*\R\s*/u', ' ', (string) $this->run($retry[0], 7)) ?? '') : '';
                }
                $out[] = $t;
            }

            return implode("\n", array_filter($out, fn ($l) => $l !== ''));
        } finally {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }

    /** Optional preprocessing (temp files always removed), then Tesseract. */
    public function ocrFile(string $imagePath): ?string
    {
        if (($missing = $this->missingLanguages()) !== []) {
            // Without "ara" Tesseract silently falls back to English and returns gibberish
            // for Arabic receipts, so don't OCR at all: empty suggestion for manual review.
            Log::warning('Tesseract OCR skipped: language data missing ('.implode(', ', $missing).'). Install ara.traineddata/eng.traineddata or set TESSERACT_TESSDATA_DIR to the folder that contains them.');

            return null;
        }
        if (! config('ai.ocr.preprocess', false)) {
            return $this->run($imagePath);
        }
        $pre = $this->preprocessor ??= new ImagePreprocessor;
        try {
            return $this->run($pre->prepare($imagePath));
        } finally {
            $pre->cleanup();
        }
    }

    /** Returns OCR text, or null when the binary is missing/failed. */
    public function run(string $imagePath, ?int $psmOverride = null, ?string $langs = null, ?string $whitelist = null): ?string
    {
        $command = [
            (string) config('ai.ocr.tesseract_path', 'tesseract'),
            $imagePath, 'stdout',
            '-l', $langs ?? self::LANGS,
        ];
        if ($whitelist !== null) {
            $command = [...$command, '-c', 'tessedit_char_whitelist='.$whitelist];
        }
        $psm = $psmOverride ?? config('ai.ocr.psm');
        if ($psm !== null && $psm !== '') {
            $command = [...$command, '--psm', (string) (int) $psm];
        }
        $command = [...$command, ...self::tessdataArgs()];
        $process = $this->makeProcess($command);
        $process->setTimeout((float) config('ai.ocr.timeout', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Tesseract OCR failed', ['exit' => $process->getExitCode(), 'error' => mb_substr($process->getErrorOutput(), 0, 300)]);

            return null;
        }

        return $process->getOutput();
    }

    /** @return list<string> ["--tessdata-dir", dir] when TESSERACT_TESSDATA_DIR is set. */
    public static function tessdataArgs(): array
    {
        $dir = config('ai.ocr.tessdata_dir');

        return is_string($dir) && $dir !== '' ? ['--tessdata-dir', $dir] : [];
    }

    /** Required languages that `tesseract --list-langs` does not report ([] if the check can't run). */
    public function missingLanguages(): array
    {
        $key = config('ai.ocr.tesseract_path', 'tesseract').'|'.config('ai.ocr.tessdata_dir');
        if (! isset(self::$langCache[$key])) {
            $p = $this->makeLanguageProcess([(string) config('ai.ocr.tesseract_path', 'tesseract'), '--list-langs', ...self::tessdataArgs()]);
            $p->setTimeout(10);
            try {
                $p->run();
            } catch (Throwable) {
                return []; // binary problems are reported by run()
            }
            if (! $p->isSuccessful()) {
                return [];
            }
            self::$langCache[$key] = array_values(array_filter(array_map('trim', preg_split('/\R/', $p->getOutput().$p->getErrorOutput()) ?: []), fn ($l) => preg_match('/^[a-z_]+$/i', $l)));
        }

        return array_values(array_diff(explode('+', self::LANGS), self::$langCache[$key]));
    }

    public static function flushLanguageCache(): void
    {
        self::$langCache = [];
    }

    /** Seam for tests (separate from OCR so OCR stubs don't affect the language check). */
    protected function makeLanguageProcess(array $command): Process
    {
        return new Process($command);
    }

    /** Seam for tests. */
    protected function makeProcess(array $command): Process
    {
        return new Process($command);
    }
}
