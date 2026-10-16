<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use Tests\TestCase;

class VoiceBarePeriodTest extends TestCase
{
    public function test_months_without_lamudda_and_filler_before_price(): void
    {
        // Synthetic sentence matching the dialect pattern QA/Yaman reported.
        $p = (new VoiceDraftParser)->parse('دفعت ايجار ثلاثه شهور اجار بيت هذا كل شهر هو 1000 ليره');
        $this->assertEquals(3000, $p['amount']);
        $this->assertSame(3, $p['items'][0]['quantity']);
        $this->assertSame(1000, $p['items'][0]['unit_price']);
    }

    public function test_months_before_recurring_price(): void
    {
        $this->assertEquals(3000, (new VoiceDraftParser)->parse('دفعت اجار تلات شهور كل شهر الف')['amount']);
    }

    public function test_plain_amount_unchanged(): void
    {
        $p = (new VoiceDraftParser)->parse('دفعت 5000 ليرة');
        $this->assertEquals(5000, $p['amount']);
        $this->assertNull($p['note']);
    }

    public function test_qa_sentences_give_3000_expense_with_warning(): void
    {
        foreach (['دفعت ايجار ثلاثه شهور اجار بيت هذا كل شهر هو 1000 ليره', 'دفعت ايجار 3 شهور كل شهر الف ليرة'] as $s) {
            $p = (new VoiceDraftParser)->parse($s);
            $this->assertEquals(3000, $p['amount'], $s);
            $this->assertSame(3, $p['items'][0]['quantity'], $s);
            $this->assertSame(1000, $p['items'][0]['unit_price'], $s);
            $this->assertSame(3000, $p['items'][0]['total'], $s);
            $this->assertSame('expense', (new \App\Finance\TransactionTypeSuggester)->suggest($p['normalized']), $s);
            $this->assertStringContainsString('تأكد من المبلغ', (string) $p['amount_warning'], $s);
        }
    }

    public function test_type_from_verb(): void
    {
        // The type comes from the shared TransactionTypeSuggester in VoiceDraftController.
        $s = new \App\Finance\TransactionTypeSuggester;
        $this->assertSame('expense', $s->suggest('اشتريت كرسي بـ500'));
        $this->assertSame('income', $s->suggest('قبضت مية الف من زبون'));
        $this->assertSame('income', $s->suggest('بعت بضاعة بألفين'));
    }
}
