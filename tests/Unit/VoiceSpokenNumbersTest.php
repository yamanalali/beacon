<?php

namespace Tests\Unit;

use App\Ai\Voice\SpokenNumberNormalizer;
use App\Ai\Voice\VoiceDraftParser;
use App\Ai\Voice\WhisperCppTranscriber;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VoiceSpokenNumbersTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            ['خمسين ألف', '50000'], ['ميتين وخمسين', '250'], ['مليون ونص', '1500000'],
            ['خمس آلاف', '5000'], ['ألفين وخمسمية', '2500'], ['تلتمية وخمسة وعشرين', '325'],
            ['خمسة عشر', '15'], ['ربع مليون', '250000'], ['ألف ونص', '1500'], ['مية', '100'],
            ['مئة وعشرة', '110'], ['ثلاثة آلاف', '3000'], ['عشرين', '20'], ['خمسة ونص', '5.5'],
            ['5 آلاف', '5000'], ['٣ آلاف و٥٠٠', '3500'], ['١٢٥٠٠', '12500'], ['مليونين', '2000000'],
        ];
    }

    /** @dataProvider numbers */
    public function test_spoken_numbers_to_digits(string $spoken, string $digits): void
    {
        $this->assertSame("دفعت $digits ليرة", (new SpokenNumberNormalizer)->normalize("دفعت $spoken ليرة"));
    }

    public function test_non_number_words_untouched(): void
    {
        $n = new SpokenNumberNormalizer;
        $this->assertSame('نص كيلو سكر', $n->normalize('نص كيلو سكر'));
        $this->assertSame('اشتريت 2 كيلو ب 500', $n->normalize('اشتريت 2 كيلو ب 500'));
    }

    public function test_parser_amount_currency_and_dates_only_when_said(): void
    {
        $p = new VoiceDraftParser;
        $r = $p->parse('دفعت خمسين ألف ليرة لمحل الأمل');
        $this->assertSame(['50000', 'SYP', null], [$r['amount'], $r['currency'], $r['date']]);

        $r = $p->parse('قبضت ميتين وخمسين');
        $this->assertSame('250', $r['amount']);
        $this->assertNull($r['currency'], 'no currency word => null, no default');
        $this->assertNull($r['date'], 'no date word => null, not today');

        $this->assertSame(now()->toDateString(), $p->parse('اليوم دفعت مية دولار')['date']);
        $this->assertSame(now()->subDay()->toDateString(), $p->parse('مبارح دفعت مية')['date']);
        $this->assertSame(now()->subDays(2)->toDateString(), $p->parse('أول مبارح دفعت مية')['date']);
        $this->assertSame('TRY', $p->parse('دفعت مية ليرة تركية')['currency']);
        $this->assertSame('AED', $p->parse('دفعت عشرين درهم')['currency']);
        $this->assertSame('USD', $p->parse('قبضت مليون ونص دولار')['currency']);
        $this->assertSame('1500000', $p->parse('قبضت مليون ونص دولار')['amount']);
    }

    public function test_whisper_uses_fixed_arabic_and_optional_prompt(): void
    {
        $w = new class extends WhisperCppTranscriber
        {
            public array $cmd = [];

            protected function makeProcess(array $command): Process
            {
                $this->cmd = $command;

                return new Process(['php', '-r', 'echo "دفعت مية";']);
            }
        };
        config(['ai.voice.whisper_prompt' => 'مصروف ودخل']);
        $w->transcribe('/tmp/a.wav', 'audio/wav');
        $this->assertSame(['-l', 'ar'], array_slice($w->cmd, 5, 2));
        $this->assertSame(['--prompt', 'مصروف ودخل'], array_slice($w->cmd, -2));

        config(['ai.voice.whisper_prompt' => '']);
        $w->transcribe('/tmp/a.wav', 'audio/wav');
        $this->assertNotContains('--prompt', $w->cmd);
        $this->assertNotContains('auto', $w->cmd);
    }
}
