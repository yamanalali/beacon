<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use Tests\TestCase;

class VoiceGenericVendorTest extends TestCase
{
    public function test_generic_people_words_are_never_the_counterparty(): void
    {
        foreach (['قبضت مية الف من زبون', 'قبضت من الزبون ألف', 'دفعت لعميل الف', 'قبضت ألفين من واحد', 'دفعت لشخص خمس مية', 'قبضت ألف من حدا'] as $s) {
            $this->assertNull((new VoiceDraftParser)->parse($s)['counterparty'], $s);
        }
    }

    public function test_amount_still_read_and_real_names_kept(): void
    {
        $p = (new VoiceDraftParser)->parse('قبضت مية الف من زبون');
        $this->assertEquals(100000, $p['amount']);
        $this->assertSame('ابو سمير', (new VoiceDraftParser)->parse('اشتريت من ابو سمير الف')['counterparty']);
    }
}
