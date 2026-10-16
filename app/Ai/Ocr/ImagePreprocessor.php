<?php

namespace App\Ai\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Optional image clean-up before local Tesseract OCR (rules §15). Runs on the
 * server only. Steps: orientation fix (Tesseract OSD: 90/180/270), grayscale,
 * upscale small images, contrast normalise and small-angle deskew. (A hard
 * threshold was benchmarked and hurt accuracy, so it is not applied.) Uses the ImageMagick CLI when present, otherwise PHP GD, otherwise
 * returns the original file. Never throws; callers must delete the returned temp
 * file via cleanup().
 */
class ImagePreprocessor
{
    /** @var list<string> */
    private array $temps = [];

    /** @return string path to OCR (the original path if preprocessing was skipped) */
    public function prepare(string $path): string
    {
        try {
            $rotate = config('ai.ocr.preprocess_osd', true) ? $this->osdRotation($path) : 0;
            $out = $this->temp();
            if ($this->magickBinary() !== null && $this->withMagick($path, $out, $rotate)) {
                return $out;
            }
            if (extension_loaded('gd') && $this->withGd($path, $out, $rotate)) {
                return $out;
            }
        } catch (Throwable $e) {
            Log::info('OCR preprocessing skipped', ['error' => $e->getMessage()]);
        }

        return $path;
    }

    public function cleanup(): void
    {
        foreach ($this->temps as $t) {
            if (is_file($t)) {
                @unlink($t);
            }
        }
        $this->temps = [];
    }

    /** Clockwise degrees to rotate so text is upright (0 when unknown). */
    public function osdRotation(string $path): int
    {
        $p = $this->makeProcess([(string) config('ai.ocr.tesseract_path', 'tesseract'), $path, 'stdout', '--psm', '0', ...TesseractReceiptExtractor::tessdataArgs()]);
        $p->setTimeout(15);
        $p->run();
        if (! $p->isSuccessful() || ! preg_match('/Rotate:\s*(\d+)/', $p->getOutput(), $m)) {
            return 0;
        }
        $conf = preg_match('/Orientation confidence:\s*([\d.]+)/', $p->getOutput(), $c) ? (float) $c[1] : 0.0;

        return $conf >= (float) config('ai.ocr.osd_min_confidence', 2.0) ? ((int) $m[1]) % 360 : 0;
    }

    public function magickBinary(): ?string
    {
        $bin = config('ai.ocr.magick_path');
        if ($bin === false || $bin === '') {
            return null;
        }
        foreach ($bin ? [$bin] : ['magick', 'convert'] as $candidate) {
            $p = $this->makeProcess([$candidate, '-version']);
            try {
                $p->run();
                if ($p->isSuccessful() && str_contains($p->getOutput(), 'ImageMagick')) {
                    return $candidate;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /** @return list<string> ImageMagick arguments between input and output. */
    public function magickArgs(int $rotate, ?int $width): array
    {
        $args = [];
        if ($rotate !== 0) {
            $args = [...$args, '-rotate', (string) $rotate];
        }
        $args = [...$args, '-colorspace', 'Gray'];
        if ($width !== null && $width < (int) config('ai.ocr.min_width', 1200)) {
            $args = [...$args, '-resize', '200%'];
        }
        $args = [...$args, '-normalize', '-deskew', '40%', '+repage', '-background', 'white', '-flatten'];

        return $args;
    }

    private function withMagick(string $in, string $out, int $rotate): bool
    {
        $size = @getimagesize($in);
        $p = $this->makeProcess([$this->magickBinary(), $in, ...$this->magickArgs($rotate, $size ? $size[0] : null), 'png:'.$out]);
        $p->setTimeout(30);
        $p->run();

        return $p->isSuccessful() && is_file($out) && filesize($out) > 0;
    }

    private function withGd(string $in, string $out, int $rotate): bool
    {
        $data = @file_get_contents($in);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if ($img === false) {
            return false;
        }
        // Palette images can crash imagerotate(); always work in truecolor.
        imagepalettetotruecolor($img);
        if ($rotate !== 0) {
            $img = imagerotate($img, (360 - $rotate) % 360, imagecolorallocate($img, 255, 255, 255)) ?: $img;
        }
        $w = imagesx($img);
        if ($w < (int) config('ai.ocr.min_width', 1200)) {
            $img = imagescale($img, $w * 2, -1, IMG_BICUBIC) ?: $img;
        }
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_CONTRAST, -30);

        return imagepng($img, $out);
    }

    private function temp(): string
    {
        $t = tempnam(sys_get_temp_dir(), 'ocrp_');
        $this->temps[] = $t;

        return $t;
    }

    /** Seam for tests. */
    protected function makeProcess(array $command): Process
    {
        return new Process($command);
    }
}
