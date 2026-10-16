<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use App\Ai\Voice\WhisperMoneyCorrector;
use Tests\TestCase;

class WhisperMoneyCorrectorTest extends TestCase
{
    public function test_fixes_benchmark_mishearings(): void
    {
        $c = new WhisperMoneyCorrector;
        $this->assertSame('اشتريت تنين كورسي خمس مية ألف', $c->correct('اشتريت تينين كورسي همس مياه الفا'));
        $this->assertSame('تايدار المحال تاتا ألف ليرة', $c->correct('تايدار المحال تاتا لفليرا'));
        $this->assertSame('قبضت، ألف، ليرة', $c->correct('قبضت، ألفا، ليرا'));
        $this->assertSame('مليون', $c->correct('أمليون'));
    }

    public function test_leaves_normal_words_alone(): void
    {
        $c = new WhisperMoneyCorrector;
        $this->assertSame('اشتريت مياه للمحل', $c->correct('اشتريت مياه للمحل'));
        $this->assertSame('دفعت 5000 ليرة اجار محل', $c->correct('دفعت 5000 ليرة اجار محل'));
    }

    public function test_water_bill_is_not_hundred(): void
    {
        $c = new WhisperMoneyCorrector;
        $this->assertSame('دفعت فاتورة مياه ألف ليرة', $c->correct('دفعت فاتورة مياه ألف ليرة'));
        $p = (new VoiceDraftParser)->parse($c->correct('دفعت فاتورة مياه ألف ليرة'));
        $this->assertEquals(1000, $p['amount']);
    }

    public function test_hams_is_a_real_word_unless_next_to_money(): void
    {
        $c = new WhisperMoneyCorrector;
        $this->assertSame('سمعت همس بالمحل', $c->correct('سمعت همس بالمحل'));
        $this->assertSame('دفعت خمس مية', $c->correct('دفعت همس مية'));
    }

    public function test_corrected_text_feeds_parser_amount(): void
    {
        $p = (new VoiceDraftParser)->parse((new WhisperMoneyCorrector)->correct('دفعت همس مياه الفا'));
        $this->assertEquals(500000, $p['amount']);
    }
}
