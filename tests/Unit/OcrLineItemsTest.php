<?php

namespace Tests\Unit;

use App\Ai\Ocr\ReceiptLineItemParser;
use App\Ai\Ocr\ReceiptTextParser;
use App\Ai\Ocr\TableInvoiceReader;
use PHPUnit\Framework\TestCase;

/** Synthetic OCR text only (no real receipts). */
class OcrLineItemsTest extends TestCase
{
    private function items(string $text): array
    {
        return (new ReceiptLineItemParser)->parse($text);
    }

    public function test_arabic_name_qty_unit_total_with_arabic_digits_and_separators(): void
    {
        $items = $this->items("بيتزا 1\n05/06/2026\nبيتزا ٤ ٧٬٠٠٠ ٢٨٬٠٠٠\nبطاطا 2 8,500 17,000\nالإجمالي 45,000 ل.س");
        $this->assertSame([
            ['description' => 'بيتزا', 'quantity' => 4, 'unit_price' => 7000, 'total' => 28000],
            ['description' => 'بطاطا', 'quantity' => 2, 'unit_price' => 8500, 'total' => 17000],
        ], $items, 'shop header «بيتزا 1», date and total lines are not items');
    }

    public function test_english_layouts(): void
    {
        $items = $this->items("Cafe 7\nCoffee 3 1,000 3,000\n2 x Tea 3.00\nPizza 2 x 7.50 15.00\nTotal 21.00 USD");
        $this->assertSame(['description' => 'Coffee', 'quantity' => 3, 'unit_price' => 1000, 'total' => 3000], $items[0]);
        // "qty x name price": the printed price is the line total; unit unknown unless qty is 1.
        $this->assertSame(['description' => 'Tea', 'quantity' => 2, 'unit_price' => null, 'total' => 3], $items[1]);
        $this->assertSame(['description' => 'Pizza', 'quantity' => 2, 'unit_price' => 7.5, 'total' => 15], $items[2]);
        $this->assertCount(3, $items);
    }

    public function test_rtl_ocr_order_and_bidi_marks(): void
    {
        $items = $this->items("فلافل 2\n10,000 \u{200F}كولا\u{200E} x 4\n\u{200E}x 1\u{200F}");
        $this->assertSame([['description' => 'كولا', 'quantity' => 4, 'unit_price' => null, 'total' => 10000]], $items);
    }

    public function test_missing_quantity_is_null_not_guessed(): void
    {
        $items = $this->items("سوبرماركت\nخبز 4,500\nMilk 18.46\nالإجمالي 4,518.46");
        $this->assertSame(['description' => 'خبز', 'quantity' => null, 'unit_price' => null, 'total' => 4500], $items[0]);
        $this->assertSame(['description' => 'Milk', 'quantity' => null, 'unit_price' => null, 'total' => 18.46], $items[1]);
    }

    public function test_restaurant_detail_line_gives_qty_and_unit(): void
    {
        $items = $this->items("مطعم تجريبي 3\nعصير البطيخ 9.79\n9.79 x 1\nشاي بالنعناع 26.34\n1 26.34\nالمبلغ المستحق 36.13");
        $this->assertSame([
            ['description' => 'عصير البطيخ', 'quantity' => 1, 'unit_price' => 9.79, 'total' => 9.79],
            ['description' => 'شاي بالنعناع', 'quantity' => 1, 'unit_price' => 26.34, 'total' => 26.34],
        ], $items);
    }

    public function test_no_items_gives_empty_array(): void
    {
        $this->assertSame([], $this->items("محل\nالإجمالي 10\n13/06/21"));
        $this->assertSame([], $this->items(''));
        $this->assertSame([], (new ReceiptTextParser)->parse("محل\nالإجمالي 10")->items);
    }

    public function test_totals_mismatch_flag_is_a_warning_value(): void
    {
        $p = new ReceiptLineItemParser;
        $items = [['description' => 'a', 'quantity' => 1, 'unit_price' => 10, 'total' => 10], ['description' => 'b', 'quantity' => null, 'unit_price' => null, 'total' => 5.5]];
        $this->assertFalse($p->totalsMismatch($items, '15.50'));
        $this->assertTrue($p->totalsMismatch($items, '17.25'), 'e.g. tax/discount: only a warning');
        $this->assertNull($p->totalsMismatch([], '10'));
        $this->assertNull($p->totalsMismatch($items, null));
        $this->assertNull($p->totalsMismatch([['description' => 'x', 'quantity' => 1, 'unit_price' => null, 'total' => null]], '10'));
    }

    public function test_parser_exposes_items_invoice_number_and_customer(): void
    {
        $text = "شركة تجريبية للحواسيب\nفاتورة مبيعات\nرقم الفاتورة / 4521 التاريخ / 15/3/2024\nمطلوب من السادة / مؤسسة الاختبار\nشاشة عرض 2 3,000 6,000\nاجمالي الفاتورة 6,000";
        $r = (new ReceiptTextParser)->parse($text);
        $this->assertSame('4521', $r->invoiceNumber);
        $this->assertSame('مؤسسة الاختبار', $r->counterparty);
        $this->assertSame('2024-03-15', $r->transactionDate);
        $this->assertSame('6000', $r->amount);
        $this->assertSame([['description' => 'شاشة عرض', 'quantity' => 2, 'unit_price' => 3000, 'total' => 6000]], $r->items);
    }

    public function test_table_result_wins_for_items_and_total(): void
    {
        $table = ['items' => [
            ['description' => 'حاسوب محمول', 'quantity' => 2, 'unit_price' => 15000, 'total' => 30000],
            ['description' => 'فأرة لاسلكية', 'quantity' => 1, 'unit_price' => 500, 'total' => 500],
        ], 'total' => 30500, 'columns' => [], 'header_text' => 'فاتورة مبيعات'];
        // Page OCR garbled the table and lost the total; the largest number must not win.
        $r = (new ReceiptTextParser)->parse("رقم الفاتورة / 77\n| 15000 | 30000\nاجمالى", null, $table);
        $this->assertSame('30500', $r->amount);
        $this->assertSame($table['items'], $r->items);
        $this->assertStringContainsString('فاتورة مبيعات', (string) $r->rawText);
    }

    public function test_table_cross_check_fills_only_exact_values(): void
    {
        $reader = new TableInvoiceReader(fn () => null);
        $this->assertSame(['description' => 'x', 'quantity' => 2, 'unit_price' => 12000, 'total' => 24000], $reader->crossCheck(['description' => 'x', 'quantity' => 2, 'unit_price' => 12000, 'total' => null]));
        $this->assertSame(['description' => 'x', 'quantity' => 1, 'unit_price' => 6000, 'total' => 6000], $reader->crossCheck(['description' => 'x', 'quantity' => 1, 'unit_price' => null, 'total' => 6000]));
        $this->assertSame(['description' => 'x', 'quantity' => 3, 'unit_price' => 2500, 'total' => 7500], $reader->crossCheck(['description' => 'x', 'quantity' => null, 'unit_price' => 2500, 'total' => 7500]));
        $this->assertSame(['description' => 'x', 'quantity' => null, 'unit_price' => 2600, 'total' => 7500], $reader->crossCheck(['description' => 'x', 'quantity' => null, 'unit_price' => 2600, 'total' => 7500]), 'no fractional qty guess');
        $this->assertSame(['description' => 'x', 'quantity' => null, 'unit_price' => null, 'total' => 5], $reader->crossCheck(['description' => 'x', 'quantity' => null, 'unit_price' => null, 'total' => 5]));
    }

    public function test_missing_total_line_uses_items_sum_never_first_number(): void
    {
        $r = (new ReceiptTextParser)->parse("فاتورة مبيعات\nحاسوب محمول 2 12,000 24,000\nفأرة لاسلكية 1 4,000 4,000\nطابعة ملونة 1 6,000 6,000\nشاشة عرض 3 2,500 7,500");
        $this->assertSame('41500', $r->amount);
    }

    public function test_no_total_line_and_no_items_gives_null_amount(): void
    {
        $r = (new ReceiptTextParser)->parse("شركة تجريبية\nرقم الفاتورة / 1002\n24000 12000\n8/6/2022");
        $this->assertNull($r->amount, 'never the first or largest number');
        $this->assertSame([], $r->items);
        $this->assertSame('2022-06-08', $r->transactionDate);
    }

    public function test_total_from_alef_maqsura_total_line(): void
    {
        $this->assertSame('41500', (new ReceiptTextParser)->parse("محل\nحاسوب 2 12000 24000\nاجمالى الفاتورة 41500")->amount);
    }

    public function test_arabic_total_line_wins_over_amount_due_and_total(): void
    {
        $p = new ReceiptTextParser;
        $this->assertSame('115', $p->parse("مطعم\nالإجمالي 115\nالمبلغ المستحق 120")->amount);
        $this->assertSame('115', $p->parse("Shop\nالمجموع 115\nTotal 130")->amount);
        $this->assertSame('120', $p->parse("مطعم\nالمبلغ المستحق 120")->amount, '«المبلغ المستحق» counts when no اجمالي line');
        $this->assertSame('50.5', $p->parse("Shop\nTotal: 50.50")->amount);
    }

    public function test_inconsistent_row_is_kept_as_read_and_counted(): void
    {
        $p = new ReceiptLineItemParser;
        $items = $p->parse("محل\nحاسوب 2 12,000 25,000\nفأرة 1 4,000 4,000");
        $this->assertSame(['description' => 'حاسوب', 'quantity' => 2, 'unit_price' => 12000, 'total' => 25000], $items[0], 'kept as read, never corrected');
        $this->assertSame(1, $p->inconsistentRows($items));
        $this->assertSame(1, $p->inconsistentRows([['description' => 'a', 'quantity' => 2, 'unit_price' => 12000, 'total' => 25000], ['description' => 'b', 'quantity' => 1, 'unit_price' => 4000, 'total' => 4000]]));
        $this->assertSame(0, $p->inconsistentRows([['description' => 'a', 'quantity' => null, 'unit_price' => 12000, 'total' => 25000]]));
    }
}
