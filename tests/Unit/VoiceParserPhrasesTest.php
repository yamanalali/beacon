<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use Tests\TestCase;

class VoiceParserPhrasesTest extends TestCase
{
    private const SENTENCE = 'دفعت اجار محل 50 الف ليرة سوريا لمدة ثلاثة اشهر';

    public function test_yaman_sentence_amount_currency_and_period_note(): void
    {
        $r = (new VoiceDraftParser)->parse(self::SENTENCE);
        $this->assertSame('50000', $r['amount'], 'digit + "الف", period never multiplies');
        $this->assertSame('SYP', $r['currency']);
        $this->assertSame('لمدة ثلاثة اشهر', $r['note']);
        $this->assertNull($r['date']);
        $this->assertNull($r['counterparty']);
    }

    public static function periods(): array
    {
        return [['لمدة شهر'], ['لمدة شهرين'], ['لمدة سنة'], ['لمدة 3 أشهر'], ['مدة سنتين'], ['لمدة اسبوعين']];
    }

    /** @dataProvider periods */
    public function test_period_variants_go_to_note_and_never_change_amount(string $period): void
    {
        $r = (new VoiceDraftParser)->parse("دفعت مية دولار اجار $period");
        $this->assertSame('100', $r['amount']);
        $this->assertSame($period, $r['note']);
    }

    public function test_small_amount_not_replaced_by_period_number(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت 5 دولار اشتراك لمدة 12 شهر');
        $this->assertSame('5', $r['amount']);
        $this->assertSame('لمدة 12 شهر', $r['note']);
    }

    public function test_syrian_currency_variants_and_no_period(): void
    {
        $p = new VoiceDraftParser;
        $this->assertSame('SYP', $p->parse('دفعت مية ليرة سورية')['currency']);
        $this->assertSame('SYP', $p->parse('دفعت مية ليرة سوريا')['currency']);
        $this->assertNull($p->parse('دفعت مية ليرة')['note']);
    }
}
