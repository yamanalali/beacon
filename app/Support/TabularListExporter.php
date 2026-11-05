<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV / XLSX downloads for list screens (transactions, enterprises).
 * Reuses SimpleXlsxWriter; formula injection is neutralised like ReportExporter.
 */
class TabularListExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function csv(array $headers, array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([$this, 'safeCell'], $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $this->safeCell($v === null ? '' : (string) $v), $row));
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function xlsx(array $headers, array $rows, string $filename, string $sheet = 'Sheet1'): StreamedResponse
    {
        $bytes = SimpleXlsxWriter::build([$sheet => array_merge([$headers], $rows)]);

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function safeCell(string $value): string
    {
        return Str::startsWith($value, ['=', '+', '@', "\t", "\r"]) || (Str::startsWith($value, '-') && ! is_numeric($value))
            ? "'".$value
            : $value;
    }
}
