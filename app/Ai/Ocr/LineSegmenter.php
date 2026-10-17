<?php

namespace App\Ai\Ocr;

/**
 * Splits a (preprocessed, upright) receipt image into horizontal text-line bands using a
 * row ink profile (PHP GD only, no extra binaries). Used for per-line OCR (Tesseract
 * --psm 7): full-page layout analysis drops short isolated lines such as an Arabic shop
 * header «بيتزا 1» or a short item line, while a single-line pass still reads them.
 *
 * Returns [] whenever the profile isn't clean (noise, photos with background, heavy
 * skew), so callers fall back to page OCR. Never throws.
 */
class LineSegmenter
{
    /** @return list<array{0: int, 1: int}> [top, bottom] pixel rows per line band */
    public function bands(string $path): array
    {
        if (! extension_loaded('gd')) {
            return [];
        }
        $data = @file_get_contents($path);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if ($img === false) {
            return [];
        }
        imagepalettetotruecolor($img); // grayscale/palette PNGs: imagecolorat() must return RGB
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 50 || $h < 20 || $w * $h > 25_000_000) {
            return [];
        }
        $step = max(1, intdiv($w, 800)); // sample columns on wide images
        $rows = [];
        $lum = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                $lum[] = ((($c >> 16) & 255) * 299 + (($c >> 8) & 255) * 587 + ($c & 255) * 114) / 1000;
            }
        }
        sort($lum);
        $bg = $lum[(int) (count($lum) * 0.9)];   // paper
        $ink = $lum[(int) (count($lum) * 0.002)]; // darkest text (text is a small share of the page)
        if ($bg - $ink < 60) {
            return []; // too little contrast for a reliable profile
        }
        $thr = ($bg + $ink) / 2;
        $cols = intdiv($w - 1, $step) + 1;
        for ($y = 0; $y < $h; $y++) {
            $dark = 0;
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                if (((($c >> 16) & 255) * 299 + (($c >> 8) & 255) * 587 + ($c & 255) * 114) / 1000 < $thr) {
                    $dark++;
                }
            }
            $rows[$y] = $dark / $cols;
        }
        // Noisy page: almost every row has ink => no usable gaps.
        $inkRows = count(array_filter($rows, fn ($r) => $r > 0.004));
        if ($inkRows > 0.85 * $h) {
            return [];
        }
        $bands = [];
        $start = null;
        foreach ($rows as $y => $r) {
            if ($r > 0.004 && $start === null) {
                $start = $y;
            } elseif ($r <= 0.004 && $start !== null) {
                $bands[] = [$start, $y - 1];
                $start = null;
            }
        }
        if ($start !== null) {
            $bands[] = [$start, $h - 1];
        }
        $bands = array_values(array_filter($bands, fn ($b) => $b[1] - $b[0] >= 3));
        if (count($bands) < 2) {
            return [];
        }
        // Merge Arabic dots / diacritics bands into their line: join bands closer than 35% of the median height.
        $heights = array_map(fn ($b) => $b[1] - $b[0] + 1, $bands);
        sort($heights);
        $med = $heights[intdiv(count($heights), 2)];
        $merged = [array_shift($bands)];
        foreach ($bands as $b) {
            $last = &$merged[count($merged) - 1];
            if ($b[0] - $last[1] < 0.35 * $med || ($b[1] - $b[0] + 1) < 0.4 * $med || ($last[1] - $last[0] + 1) < 0.4 * $med) {
                $last[1] = $b[1];
            } else {
                $merged[] = $b;
            }
            unset($last);
        }
        $heights = array_map(fn ($b) => $b[1] - $b[0] + 1, $merged);
        sort($heights);
        $med = $heights[intdiv(count($heights), 2)];
        // A band much taller than a line means skew/columns/pictures: let page OCR handle it.
        if (count($merged) > 80 || max($heights) > 3 * $med) {
            return [];
        }
        $pad = (int) ceil(0.3 * $med);

        return array_map(fn ($b) => [max(0, $b[0] - $pad), min($h - 1, $b[1] + $pad)], $merged);
    }

    /**
     * Writes each band as a PNG scaled so the text is about $inkHeight px tall (Tesseract's
     * LSTM reads short Arabic lines far better near ~16-26 px than at 30+ px), with a white
     * border, and returns the temp paths (caller deletes them).
     *
     * @return list<string>
     */
    public function crop(string $path, array $bands, int $inkHeight = 18): array
    {
        $img = @imagecreatefromstring((string) @file_get_contents($path));
        if ($img === false) {
            return [];
        }
        imagepalettetotruecolor($img);
        $w = imagesx($img);
        $out = [];
        foreach ($bands as [$top, $bottom]) {
            $bh = $bottom - $top + 1;
            $scale = max(0.2, min(3.0, $inkHeight / max(1, $bh / 1.6))); // bands carry 30% padding per side
            $band = imagecreatetruecolor($w, $bh);
            imagecopy($band, $img, 0, 0, 0, $top, $w, $bh);
            $sw = max(1, (int) round($w * $scale));
            $sh = max(1, (int) round($bh * $scale));
            $scaled = imagescale($band, $sw, $sh, IMG_BICUBIC) ?: $band;
            $border = max(10, $sh);
            $dst = imagecreatetruecolor($sw + 2 * $border, $sh + 2 * $border);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopy($dst, $scaled, $border, $border, 0, 0, $sw, $sh);
            $file = tempnam(sys_get_temp_dir(), 'ocrl_');
            imagepng($dst, $file);
            $out[] = $file;
        }

        return $out;
    }
}
