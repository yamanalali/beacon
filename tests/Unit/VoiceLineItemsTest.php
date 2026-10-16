<?php

namespace Tests\Unit;

use App\Ai\Voice\SpokenNumberNormalizer;
use App\Ai\Voice\VoiceDraftParser;
use Tests\TestCase;

class VoiceLineItemsTest extends TestCase
{
    public function test_yaman_monthly_rent_sentence(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت ايجار محل 3000 الاف لمدة 3 اشهر كل شهر الف');
        $this->assertSame('3000', $r['amount']);
        $this->assertSame([['description' => 'إيجار محل (شهري)', 'quantity' => 3, 'unit_price' => 1000, 'total' => 3000]], $r['items']);
        $this->assertFalse($r['items_mismatch']);
        $this->assertStringContainsString('لمدة 3 اشهر', $r['note']);
        $this->assertNotNull($r['amount_warning'], '«3000 الاف» is ambiguous wording');
    }

    public function test_monthly_without_total_amount_is_items_sum(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت اجار بيت كل شهر 500 دولار لمدة شهرين');
        $this->assertSame('1000', $r['amount']);
        $this->assertSame([['description' => 'إيجار بيت (شهري)', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000]], $r['items']);
        $this->assertFalse($r['items_mismatch']);
        $this->assertNull($r['amount_warning']);
    }

    public function test_spoken_total_differs_from_items_sets_mismatch_and_warning(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت ايجار محل 2500 لمدة 3 اشهر كل شهر الف');
        $this->assertSame('2500', $r['amount']);
        $this->assertTrue($r['items_mismatch']);
        $this->assertStringContainsString('تأكد من المبلغ', $r['amount_warning']);
    }

    public function test_weekly_and_bil_forms(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت للعامل بالأسبوع 200 لمدة 4 اسابيع');
        $this->assertSame(4, $r['items'][0]['quantity']);
        $this->assertSame(200, $r['items'][0]['unit_price']);
        $this->assertStringContainsString('(أسبوعي)', $r['items'][0]['description']);
        $this->assertSame('800', $r['amount']);
    }

    public function test_multiple_goods(): void
    {
        $r = (new VoiceDraftParser)->parse('اشتريت 2 كرسي بـ500 وطاولة بـ1000');
        $this->assertSame('2000', $r['amount']);
        $this->assertSame([
            ['description' => 'كرسي', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000],
            ['description' => 'طاولة', 'quantity' => 1, 'unit_price' => 1000, 'total' => 1000],
        ], $r['items']);
        $this->assertFalse($r['items_mismatch']);
        $this->assertNull($r['amount_warning']);
    }

    public function test_clear_sentences_have_no_items_and_no_warning(): void
    {
        foreach (['دفعت اجار محل 50 الف ليرة سوريا لمدة ثلاثة اشهر', 'قبضت مبارح ٢٠٠ دولار من سامر'] as $s) {
            $r = (new VoiceDraftParser)->parse($s);
            $this->assertSame([], $r['items'], $s);
            $this->assertNull($r['amount_warning'], $s);
            $this->assertFalse($r['items_mismatch']);
        }
    }

    public function test_thousand_word_after_full_number_is_not_multiplied(): void
    {
        $n = new SpokenNumberNormalizer;
        $this->assertSame('3000', trim($n->normalize('3000 الاف')));
        $this->assertTrue($n->ambiguous);
        $this->assertSame('3000', trim($n->normalize('3 الاف')));
        $this->assertFalse($n->ambiguous);
        $this->assertSame('50000', trim($n->normalize('50 الف')));
        $this->assertSame('1000', trim($n->normalize('الف')));
        $this->assertSame('كل شهر 1000', trim($n->normalize('كل شهر الف')));
    }
}
