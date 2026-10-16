<?php

namespace Tests\Unit\Finance;

use App\Finance\ReceiptTextCategorizer;
use PHPUnit\Framework\TestCase;

class ReceiptTextCategorizerTest extends TestCase
{
    private const PIZZA = "بيتزا 1\nدرب مسرح أبوظبي - الإمارات العربية المتحدة\nالتاريخ 1/22/21\nسلطة الجبنة 17.24\nعصير البطيخ 4.30\nالمبلغ المستحق 21.54";

    private const EXPENSE = ['rent' => 'إيجار', 'utilities' => 'كهرباء', 'salaries' => 'رواتب', 'transport' => 'نقل', 'meals' => 'طعام ومشروبات', 'other_expense' => 'أخرى'];

    public function test_pizza_receipt_maps_to_food_category(): void
    {
        $s = (new ReceiptTextCategorizer)->suggest(self::PIZZA, self::EXPENSE);
        $this->assertSame('meals', $s?->category);
        $this->assertTrue($s->needsReview);
    }

    public function test_food_receipt_without_food_category_in_config_returns_null(): void
    {
        $allowed = self::EXPENSE;
        unset($allowed['meals']);
        $this->assertNull((new ReceiptTextCategorizer)->suggest(self::PIZZA, $allowed));
    }

    public function test_english_and_other_groups(): void
    {
        $c = new ReceiptTextCategorizer;
        $this->assertSame('meals', $c->suggest('RESTAURANT Cheese Salad 17.24 Total 21.54', self::EXPENSE)?->category);
        $this->assertSame('transport', $c->suggest('محطة وقود — بنزين 40 لتر', self::EXPENSE)?->category);
        $this->assertSame('utilities', $c->suggest('فاتورة كهرباء شهر أيلول', self::EXPENSE)?->category);
        $this->assertSame('rent', $c->suggest('إيجار المحل لشهر تشرين', self::EXPENSE)?->category);
    }

    public function test_unknown_text_returns_null(): void
    {
        $c = new ReceiptTextCategorizer;
        $this->assertNull($c->suggest("فاتورة رقم 123\nالمجموع 50.00", self::EXPENSE));
        $this->assertNull($c->suggest(null, self::EXPENSE));
        $this->assertNull($c->suggest('', self::EXPENSE));
        // No substring false positives: «أسماء» must not match «ماء».
        $this->assertNull($c->suggest('أسماء الموظفين', self::EXPENSE));
    }

    public function test_tie_between_groups_returns_null(): void
    {
        $this->assertNull((new ReceiptTextCategorizer)->suggest('إيجار وكهرباء', self::EXPENSE));
    }

    public function test_never_returns_category_outside_allowed_list(): void
    {
        $c = new ReceiptTextCategorizer;
        foreach ([self::PIZZA, 'fuel diesel', 'salary payroll', 'internet', 'rent', 'taxi'] as $text) {
            foreach ([['other_income' => 'دخل آخر'], ['general_sales' => 'مبيعات'], self::EXPENSE] as $allowed) {
                $s = $c->suggest($text, $allowed);
                $this->assertTrue($s === null || array_key_exists($s->category, $allowed));
            }
        }
    }
}
