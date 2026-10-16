<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use Tests\TestCase;

class VoiceVendorLamPrefixTest extends TestCase
{
    public function test_lam_glued_to_title_is_stripped(): void
    {
        $p = (new VoiceDraftParser)->parse('دفعت الف لابو سمير');
        $this->assertSame('ابو سمير', $p['counterparty']);
        $this->assertEquals(1000, $p['amount']);
        $this->assertSame('أم أحمد', (new VoiceDraftParser)->parse('دفعت خمس مية لأم أحمد')['counterparty']);
    }

    public function test_lam_on_other_words_is_not_a_name(): void
    {
        $this->assertNull((new VoiceDraftParser)->parse('دفعت الف ليرة')['counterparty']);
        $this->assertNull((new VoiceDraftParser)->parse('دفعت لعميل الف')['counterparty']);
    }
}
