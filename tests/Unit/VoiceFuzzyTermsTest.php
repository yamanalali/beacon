<?php

namespace Tests\Unit;

use App\Ai\Voice\VoiceDraftParser;
use Tests\TestCase;

class VoiceFuzzyTermsTest extends TestCase
{
    /** Yaman's real Windows whisper transcript (ليرة اجار heard as «ليرائي جر»). */
    private const REAL = 'دفعت خمسين ألف ليرائي جر محل اليوم عن ثلاث شهور';

    public function test_real_transcript(): void
    {
        $r = (new VoiceDraftParser)->parse(self::REAL);
        $this->assertSame('50000', $r['amount']);
        $this->assertSame('SYP', $r['currency']);
        $this->assertNull($r['counterparty']);
        $this->assertSame('عن ثلاث شهور', $r['note']);
        $this->assertSame(now()->toDateString(), $r['date']);
        $this->assertStringContainsString('اجار محل', $r['normalized']);
    }

    public static function liras(): array
    {
        return [['ليرة'], ['ليره'], ['ليرا'], ['ليرات'], ['ليرائي'], ['لير']];
    }

    /** @dataProvider liras */
    public function test_lira_spellings_give_syp(string $w): void
    {
        $this->assertSame('SYP', (new VoiceDraftParser)->parse("دفعت مية $w")['currency']);
    }

    public function test_no_lira_false_positive_and_no_parser_default(): void
    {
        $p = new VoiceDraftParser;
        $this->assertNull($p->parse('دفعت مية للليرون')['currency']);
        $this->assertNull($p->parse('دفعت مية اجار')['currency'], 'parser stays null; SYP default is in the voice endpoint');
    }

    public function test_jar_rewritten_to_rent_only_in_context(): void
    {
        $p = new VoiceDraftParser;
        $this->assertSame('دفعت اجار محل', $p->normalizeTerms('دفعت جر محل'));
        $this->assertSame('مية ليرا اجار', $p->normalizeTerms('مية ليرا ئي جر'));
        $this->assertSame('جر الكرسي', $p->normalizeTerms('جر الكرسي'));
    }

    public function test_an_with_digits_period_never_multiplies(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت 50 الف ليرا جر محل عن 3 اشهر');
        $this->assertSame('50000', $r['amount']);
        $this->assertSame('عن 3 اشهر', $r['note']);
    }

    public function test_an_alone_is_not_a_period_or_vendor(): void
    {
        $r = (new VoiceDraftParser)->parse('دفعت مية دولار من عن');
        $this->assertNull($r['note']);
        $this->assertNull($r['counterparty']);
        $this->assertNull((new VoiceDraftParser)->parse('دفعت عن الشغل مية دولار')['note']);
    }

    public static function nonVendors(): array
    {
        return [['دفعت مية دولار عن'], ['دفعت مية دولار من اليوم'], ['دفعت مية دولار من عن'], ['دفعت مية دولار الى من']];
    }

    /** @dataProvider nonVendors */
    public function test_prepositions_and_date_words_never_vendor(string $t): void
    {
        $this->assertNull((new VoiceDraftParser)->parse($t)['counterparty']);
    }

    public function test_dafa3t_gives_expense(): void
    {
        $this->assertSame('expense', app(\App\Finance\TransactionTypeSuggester::class)->suggest(self::REAL));
    }

    public function test_real_vendor_still_found(): void
    {
        $this->assertSame('أبو سمير', (new VoiceDraftParser)->parse('دفعت مية ليرة لمحل أبو سمير')['counterparty']);
    }
}
