<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal .xlsx writer (Office Open XML) with no paid packages.
 * Sheets are arrays of string/number/null cells; first row is usually headers.
 */
class SimpleXlsxWriter
{
    /** @param array<string, list<list<string|int|float|null>>> $sheets name => rows */
    public static function build(array $sheets): string
    {
        if ($sheets === []) {
            throw new RuntimeException('At least one sheet is required.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Unable to open the workbook archive.');
        }

        $names = array_keys($sheets);
        $shared = [];
        $sharedIndex = [];
        $sheetXml = [];

        foreach ($sheets as $name => $rows) {
            $sheetXml[$name] = self::sheetXml($rows, $shared, $sharedIndex);
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($names)));
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($names)));
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/sharedStrings.xml', self::sharedStrings($shared));

        $i = 1;
        foreach ($names as $name) {
            $zip->addFromString('xl/worksheets/sheet'.$i.'.xml', $sheetXml[$name]);
            $i++;
        }

        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** @param list<list<string|int|float|null>> $rows @param list<string> $shared @param array<string,int> $sharedIndex */
    private static function sheetXml(array $rows, array &$shared, array &$sharedIndex): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $rowNum = $r + 1;
            $out .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $c => $value) {
                $ref = self::col($c).$rowNum;
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && ! preg_match('/^0\d/', $value))) {
                    $out .= '<c r="'.$ref.'"><v>'.self::xml((string) $value).'</v></c>';

                    continue;
                }
                $text = (string) $value;
                if (! isset($sharedIndex[$text])) {
                    $sharedIndex[$text] = count($shared);
                    $shared[] = $text;
                }
                $out .= '<c r="'.$ref.'" t="s"><v>'.$sharedIndex[$text].'</v></c>';
            }
            $out .= '</row>';
        }

        return $out.'</sheetData></worksheet>';
    }

    /** @param list<string> $names */
    private static function workbook(array $names): string
    {
        $sheets = '';
        foreach (array_values($names) as $i => $name) {
            $sheets .= '<sheet name="'.self::xml($name).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private static function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $rels .= '<Relationship Id="rId'.($count + 2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private static function contentTypes(int $sheets): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheets; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="1"><xf/></cellXfs>'
            .'</styleSheet>';
    }

    /** @param list<string> $shared */
    private static function sharedStrings(array $shared): string
    {
        $count = count($shared);
        $items = '';
        foreach ($shared as $text) {
            $items .= '<si><t xml:space="preserve">'.self::xml($text).'</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">'
            .$items
            .'</sst>';
    }

    private static function col(int $index): string
    {
        $name = '';
        $n = $index;
        do {
            $name = chr(65 + ($n % 26)).$name;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $name;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
