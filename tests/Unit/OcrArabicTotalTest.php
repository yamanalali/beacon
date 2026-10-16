<?php

namespace Tests\Unit;

use App\Ai\Ocr\ReceiptTextParser;
use App\Ai\Ocr\TesseractReceiptExtractor;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Synthetic OCR text shaped like an Arabic restaurant invoice (no real receipt data). */
class OcrArabicTotalTest extends TestCase
{
    private const INVOICE = "مطعم الزيتون 2\nطريق المثال - منطقة العرض - الإمارات\nفاتورة\nالطلبية: تذكرة - PM 2:32\nأمين الصندوق: المالك\nسلطة الجبنة 17.24\n14.24 x 1\n+ صغير (3.00)\nعصير البطيخ 4.30\n4.30 x 1\nالمبلغ المستحق 21.54\n1/22/21, 4:56 PM";

    protected function setUp(): void
    {
        parent::setUp();
        TesseractReceiptExtractor::flushLanguageCache();
    }

    private function parse(string $t)
    {
        return (new ReceiptTextParser)->parse($t);
    }

    public function test_amount_comes_from_payable_total_line_not_largest_number(): void
    {
        $r = $this->parse(self::INVOICE);
        $this->assertSame('21.54', $r->amount);
        $this->assertSame('مطعم الزيتون 2', $r->counterparty);
        $this->assertSame('2021-01-22', $r->transactionDate);
    }

    public function test_total_keywords_priority_and_subtotal_ignored(): void
    {
        $this->assertSame('115', $this->parse("متجر\nالمجموع الفرعي 100\nضريبة 15\nالإجمالي 115\nنقداً 200")->amount);
        $this->assertSame('50.5', $this->parse("Shop\nSubtotal 45\nTotal: 50.50\nCash 100")->amount);
        $this->assertSame('99', $this->parse("محل\nالمجموع 99\nرقم 123456")->amount);
    }

    public function test_arabic_indic_digits(): void
    {
        $r = $this->parse("محل\nالمبلغ المستحق ٢١٫٥٤\n٢٢/٠١/٢٠٢١");
        $this->assertSame('21.54', $r->amount);
        $this->assertSame('2021-01-22', $r->transactionDate);
    }

    public function test_date_and_currency_left_empty_when_not_read(): void
    {
        $r = $this->parse(self::INVOICE);
        $this->assertNull($r->currency, 'address country must not imply currency');
        $this->assertNull($this->parse("محل\nالإجمالي 10")->transactionDate);
        $this->assertNull($this->parse("محل\nالإجمالي 10\n05/06/21")->transactionDate, 'ambiguous d/m/yy stays empty');
        $this->assertSame('2021-06-13', $this->parse("محل\n13/06/21")->transactionDate);
    }

    public function test_currency_only_from_explicit_tokens(): void
    {
        $this->assertSame('AED', $this->parse("محل\nالإجمالي 10 درهم")->currency);
        $this->assertSame('SYP', $this->parse("محل\nالإجمالي 5000 ل.س")->currency);
        $this->assertSame('USD', $this->parse("Shop\nTotal $12")->currency);
        $this->assertSame('SAR', $this->parse("محل\nالإجمالي 10 SAR")->currency);
        $this->assertNull($this->parse("مثل سعر المحل\nالإجمالي 10")->currency, 'no "ل س" across words');
    }

    private function extractor(string $langs, array &$calls): TesseractReceiptExtractor
    {
        return new class(new ReceiptTextParser, $langs, $calls) extends TesseractReceiptExtractor
        {
            public function __construct(ReceiptTextParser $p, private string $langs, public array &$calls)
            {
                parent::__construct($p);
            }

            protected function makeLanguageProcess(array $command): Process
            {
                $this->calls[] = $command;

                return new Process(['php', '-r', 'echo $argv[1];', $this->langs]);
            }

            protected function makeProcess(array $command): Process
            {
                $this->calls[] = $command;

                return new Process(['php', '-r', 'echo "محل\nالإجمالي 10";']);
            }
        };
    }

    public function test_missing_arabic_data_skips_ocr_instead_of_reading_english(): void
    {
        config(['ai.ocr.preprocess' => false]);
        $calls = [];
        $e = $this->extractor("List of available languages in \"/x/\" (1):\neng\n", $calls);
        $this->assertSame(['ara'], $e->missingLanguages());
        $this->assertNull($e->ocrFile('/img.png'));
        $this->assertCount(1, $calls, 'only the --list-langs check ran, no OCR');
    }

    public function test_tessdata_dir_passed_only_when_set_and_langs_always_ara_eng(): void
    {
        config(['ai.ocr.preprocess' => false, 'ai.ocr.psm' => 6, 'ai.ocr.tessdata_dir' => null]);
        $calls = [];
        $e = $this->extractor("ara\neng\nosd\n", $calls);
        $this->assertSame("محل\nالإجمالي 10", $e->ocrFile('/img.png'));
        $this->assertNotContains('--tessdata-dir', $calls[1]);
        $this->assertSame(['-l', 'ara+eng'], array_slice($calls[1], 3, 2));

        TesseractReceiptExtractor::flushLanguageCache();
        config(['ai.ocr.tessdata_dir' => 'C:\\Tesseract-OCR\\tessdata']);
        $calls = [];
        $e = $this->extractor("ara\neng\n", $calls);
        $e->ocrFile('/img.png');
        $this->assertSame(['--tessdata-dir', 'C:\\Tesseract-OCR\\tessdata'], array_slice($calls[0], -2));
        $this->assertSame(['--tessdata-dir', 'C:\\Tesseract-OCR\\tessdata'], array_slice($calls[1], -2));
    }
}
