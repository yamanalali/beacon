<?php

namespace App\Services\Bookkeeping;

use App\Enums\PaymentStatus;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionInvoiceExporter
{
    public function pdf(FinancialTransaction $transaction, ?User $signer = null): StreamedResponse
    {
        $transaction->loadMissing(['enterprise', 'items', 'creator:id,name', 'settlements']);

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('Cannot create mPDF temp directory.');
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';

        $signatureDataUri = null;
        if ($signer?->signature_path && Storage::disk('local')->exists($signer->signature_path)) {
            $bytes = Storage::disk('local')->get($signer->signature_path);
            $signatureDataUri = 'data:image/png;base64,'.base64_encode($bytes);
        }

        $logoDataUri = $this->resizedLogoDataUri(public_path('images/brand/beacon-logo.png'), 110);

        $categoryLabel = app(TransactionCategoryCatalog::class)->label($transaction->type->value, $transaction->category);
        $paymentStatusClass = match ($transaction->payment_status) {
            PaymentStatus::Paid => 'status-paid',
            PaymentStatus::PartiallyPaid => 'status-partial',
            default => 'status-unpaid',
        };

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => $dir,
            'tempDir' => $tempDir,
            'default_font' => 'xbriyaz',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 14,
        ]);
        $mpdf->SetDirectionality($dir);
        $mpdf->SetTitle(($locale === 'en' ? 'Invoice' : 'فاتورة').' '.$transaction->public_id);
        $mpdf->WriteHTML(view('exports.transaction-invoice-pdf', [
            'transaction' => $transaction,
            'enterprise' => $transaction->enterprise,
            'items' => $transaction->items,
            'locale' => $locale,
            'dir' => $dir,
            'signature' => $signatureDataUri,
            'signerName' => $signer?->name,
            'creatorName' => $transaction->creator?->name,
            'categoryLabel' => $categoryLabel,
            'outstanding' => Money::fromMinor($transaction->outstandingMinor()),
            'paymentStatusClass' => $paymentStatusClass,
            'logo' => $logoDataUri,
        ])->render());

        $content = $mpdf->Output('', Destination::STRING_RETURN);
        $filename = 'invoice-'.$transaction->public_id;

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * mPDF often ignores CSS height on data-URI images; embed a small PNG instead.
     */
    private function resizedLogoDataUri(string $path, int $maxWidth): ?string
    {
        if (! is_file($path) || ! function_exists('imagecreatefrompng')) {
            return is_file($path)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
                : null;
        }

        $source = @imagecreatefrompng($path);
        if ($source === false) {
            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);

            return null;
        }

        $width = min($maxWidth, $srcW);
        $height = (int) max(1, round($srcH * ($width / $srcW)));
        $dest = imagecreatetruecolor($width, $height);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $width, $height, $transparent);
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $width, $height, $srcW, $srcH);

        ob_start();
        imagepng($dest, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($dest);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
