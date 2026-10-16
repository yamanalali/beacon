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

    public function test_corrected_text_feeds_parser_amount(): void
    {
        $p = (new VoiceDraftParser)->parse((new WhisperMoneyCorrector)->correct('دفعت همس مياه الفا'));
        $this->assertEquals(500000, $p['amount']);
    }
}
