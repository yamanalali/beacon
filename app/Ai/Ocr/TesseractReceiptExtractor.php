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
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/bmp', 'image/gif'];

    public function __construct(private readonly ReceiptTextParser $parser) {}

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

            $raw = $this->run($tmp);
        } catch (Throwable $e) {
            Log::warning('Tesseract OCR unavailable', ['document_id' => $document->id, 'error' => $e->getMessage()]);

            return new ReceiptExtractionResult(failed: true);
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return $raw === null ? new ReceiptExtractionResult(failed: true) : $this->parser->parse($raw);
    }

    /** Returns OCR text, or null when the binary is missing/failed. */
    public function run(string $imagePath): ?string
    {
        $process = $this->makeProcess([
            (string) config('ai.ocr.tesseract_path', 'tesseract'),
            $imagePath, 'stdout',
            '-l', (string) config('ai.ocr.tesseract_langs', 'ara+eng'),
        ]);
        $process->setTimeout((float) config('ai.ocr.timeout', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Tesseract OCR failed', ['exit' => $process->getExitCode(), 'error' => mb_substr($process->getErrorOutput(), 0, 300)]);

            return null;
        }

        return $process->getOutput();
    }

    /** Seam for tests. */
    protected function makeProcess(array $command): Process
    {
        return new Process($command);
    }
}
