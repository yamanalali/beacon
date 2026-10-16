<?php

namespace Tests\Unit;

use App\Finance\TransactionTypeSuggester;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransactionTypeSuggesterSpokenTest extends TestCase
{
    public static function income(): array
    {
        return [
            'قبضت' => ['قبضت خمسين ألف من أبو محمد'],
            'استلمت' => ['استلمت حق الطلبية اليوم'],
            'دخلّي' => ['دخلّي مية ألف'],
            'دخلي' => ['اليوم دخلي ٣٠٠ ألف'],
            'مبيع' => ['مبيع كيلو جبنة'],
            'مبيعات' => ['مبيعات اليوم ٢٠٠ ألف'],
            'إيراد' => ['إيراد من المحل'],
            'بعت' => ['بعت ثلاث قطع بـ٩٠ ألف'],
            'بعتها' => ['بعتها بخمسين'],
        ];
    }

    public static function expense(): array
    {
        return [
            'بعتلو مصاري' => ['بعتلو مصاري'],
            'بعت لها' => ['بعت لها ٢٠ ألف'],
            'بعتلها' => ['بعتلها حق الغاز'],
            'بعت على' => ['بعت على الحساب'],
            'دفعت إيجار' => ['دفعت إيجار'],
            'اشتريت' => ['اشتريت خضرة بعشرين ألف'],
            'مدخلي (no false hit)' => ['مدخل المحل تصليح'],
        ];
    }

    #[DataProvider('income')]
    public function test_spoken_income(string $text): void
    {
        $this->assertSame('income', (new TransactionTypeSuggester)->suggest($text));
    }

    #[DataProvider('expense')]
    public function test_spoken_expense(string $text): void
    {
        $this->assertSame('expense', (new TransactionTypeSuggester)->suggest($text));
    }
}
