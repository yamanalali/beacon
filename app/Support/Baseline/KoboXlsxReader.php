<?php

namespace App\Support\Baseline;

use RuntimeException;

/**
 * Lightweight KoBo/ODK XLSX reader (sharedStrings + first sheet only).
 * Avoids a paid/extra spreadsheet package for one-off imports.
 */
class KoboXlsxReader
{
    /**
     * @return array{headers: array<string,string>, rows: list<array<string,string>>}
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('ملف الاستيراد غير موجود.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('تعذّر فتح ملف Excel.');
        }

        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($ss) && $ss !== '') {
            preg_match_all('/<si>(.*?)<\/si>/s', $ss, $m);
            foreach ($m[1] as $i => $si) {
                preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $t);
                $shared[$i] = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! is_string($sheet) || $sheet === '') {
            throw new RuntimeException('ورقة البيانات الأولى فارغة.');
        }

        preg_match_all('/<c\b([^>]*)>(?:<v>(.*?)<\/v>)?/s', $sheet, $cells, PREG_SET_ORDER);
        $grid = [];
        foreach ($cells as $cell) {
            $attrs = $cell[1];
            if (! preg_match('/\br="([A-Z]+)(\d+)"/', $attrs, $rm)) {
                continue;
            }
            $col = $rm[1];
            $row = (int) $rm[2];
            $t = preg_match('/\bt="([^"]+)"/', $attrs, $tm) ? $tm[1] : '';
            $v = $cell[2] ?? '';
            if ($t === 's') {
                $v = $shared[(int) $v] ?? $v;
            }
            $grid[$row][$col] = is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v) ?? $v) : (string) $v;
        }

        if ($grid === []) {
            throw new RuntimeException('لا توجد صفوف في الملف.');
        }

        ksort($grid);
        $headerRow = $grid[1] ?? null;
        if ($headerRow === null) {
            throw new RuntimeException('صف الترويسة مفقود.');
        }
        uksort($headerRow, fn ($a, $b) => strlen($a) <=> strlen($b) ?: strcmp($a, $b));

        $rows = [];
        foreach ($grid as $rowNum => $cols) {
            if ($rowNum === 1) {
                continue;
            }
            uksort($cols, fn ($a, $b) => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
            $rows[] = $cols;
        }

        return ['headers' => $headerRow, 'rows' => $rows];
    }
}
