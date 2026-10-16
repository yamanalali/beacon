<?php

namespace Tests\Unit\Finance;

use App\Finance\TransactionTypeSuggester;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransactionTypeSuggesterTest extends TestCase
{
    public function test_null_or_blank_text_returns_null(): void
    {
        $s = new TransactionTypeSuggester;
        $this->assertNull($s->suggest(null));
        $this->assertNull($s->suggest("  \n "));
    }

    public function test_ordinary_receipt_defaults_to_expense(): void
    {
        $this->assertSame('expense', (new TransactionTypeSuggester)->suggest("مؤسسة النور\nالإجمالي 250 ل.س\nTotal 250"));
    }

    public static function incomeTexts(): array
    {
        return [['مقبوضات نقدية'], ['سند قبض رقم 12'], ['إيصال استلام'], ['ايصال استلام'], ['تم استلام مبلغ 500'],
            ['دفعة من زبون'], ['دفعة من الزبون أحمد'], ['دفعة من عميل'], ['Received from Ahmad'], ['PAYMENT RECEIVED'],
            ['Receipt Voucher #4'], ['Customer payment']];
    }

    #[DataProvider('incomeTexts')]
    public function test_receipt_of_payment_wording_is_income(string $text): void
    {
        $this->assertSame('income', (new TransactionTypeSuggester)->suggest($text));
    }

    public function test_options_are_expense_and_income_from_enum(): void
    {
        $this->assertSame([['value' => 'expense', 'label' => 'مصروف'], ['value' => 'income', 'label' => 'دخل آخر']], TransactionTypeSuggester::options());
    }
}
