<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use App\Ai\Voice\WhisperMoneyCorrector;
use Tests\TestCase;

class VoiceDigitHundredWarningTest extends TestCase
{
    public function test_digit_plus_hundred_thousand_warns_but_keeps_amount(): void
    {
        $p = (new VoiceDraftParser)->parse((new WhisperMoneyCorrector)->correct('دفعت 3 مياه ألف'));
        $this->assertEquals(300000, $p['amount']);
        $this->assertStringContainsString('300000', (string) $p['amount_warning']);
    }

    public function test_spoken_number_and_water_bill_do_not_warn(): void
    {
        $this->assertNull((new VoiceDraftParser)->parse((new WhisperMoneyCorrector)->correct('دفعت خمس مياه ألف'))['amount_warning']);
        $this->assertNull((new VoiceDraftParser)->parse((new WhisperMoneyCorrector)->correct('دفعت فاتورة مياه ألف ليرة'))['amount_warning']);
    }
}
