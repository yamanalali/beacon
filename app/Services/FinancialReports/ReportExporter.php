<?php

namespace App\Services\FinancialReports;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * CSV and Excel (.xlsx) export without third-party packages.
 * XLSX is written as a minimal Office Open XML package with ZipArchive.
 */
class ReportExporter
{
    public function csv(FinancialReport $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly.
            foreach ($this->lines($report) as $line) {
                fputcsv($out, array_map([$this, 'safeCell'], $line));
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function xlsx(FinancialReport $report, string $filename): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create xlsx.');
        }

        $rowsXml = '';
        foreach ($this->lines($report) as $i => $line) {
            $cells = '';
            foreach ($line as $value) {
                $value = (string) $value;
                $cells .= preg_match('/^-?\d+(\.\d+)?$/', $value)
                    ? '<c t="n"><v>'.$value.'</v></c>'
                    : '<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($this->safeCell($value), ENT_XML1).'</t></is></c>';
            }
            $rowsXml .= '<row r="'.($i + 1).'">'.$cells.'</row>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><sheetData>'.$rowsXml.'</sheetData></worksheet>');
        $zip->close();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return list<list<string>>
     */
    public function lines(FinancialReport $report): array
    {
        $lines = [[$report->title], [$report->periodLabel]];
        foreach ($report->sections as $section) {
            $lines[] = [];
            $lines[] = [$section['title']];
            $lines[] = $section['columns'];
            foreach ($section['rows'] as $row) {
                $lines[] = $row;
            }
        }
        $lines[] = [];
        foreach ($report->notes as $note) {
            $lines[] = [$note];
        }

        return $lines;
    }

    /** Prevent spreadsheet formula injection from user-entered text. */
    public function safeCell(string $value): string
    {
        return Str::startsWith($value, ['=', '+', '@', "\t", "\r"]) || (Str::startsWith($value, '-') && ! is_numeric($value))
            ? "'".$value
            : $value;
    }
}
