<?php

namespace Tests\Unit;

use App\Ai\Voice\WhisperCppTranscriber;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WhisperUtf8Test extends TestCase
{
    /** Fake whisper: writes $fileText to "<-of>.txt" (if given) and prints $stdout. */
    private function whisper(?string $fileText, string $stdout = '', bool $windows = false): WhisperCppTranscriber
    {
        return new class($fileText, $stdout, $windows) extends WhisperCppTranscriber
        {
            public array $cmd = [];

            public function __construct(private ?string $fileText, private string $stdout, private bool $windows) {}

            protected function isWindows(): bool
            {
                return $this->windows;
            }

            protected function makeProcess(array $command): Process
            {
                $this->cmd = $command;
                $base = $command[array_search('-of', $command, true) + 1];
                if ($this->fileText !== null) {
                    file_put_contents($base.'.txt', $this->fileText);
                }

                return new Process(['php', '-r', 'echo base64_decode($argv[1]);', base64_encode($this->stdout)]);
            }
        };
    }

    public function test_reads_utf8_arabic_from_output_file_and_deletes_temp_files(): void
    {
        $w = $this->whisper("\u{FEFF}دفعت خمسين الف ليرة اجار محل اليوم\n", '?????? ?????? ??????');
        $this->assertSame('دفعت خمسين الف ليرة اجار محل اليوم', $w->transcribe('/tmp/a.wav', 'audio/wav'));
        $i = array_search('-otxt', $w->cmd, true);
        $this->assertNotFalse($i);
        $this->assertSame('-of', $w->cmd[$i + 1]);
        $base = $w->cmd[$i + 2];
        $this->assertFileDoesNotExist($base.'.txt');
        $this->assertFileDoesNotExist($base);
        $this->assertSame(['-l', 'ar'], array_slice($w->cmd, 5, 2));
    }

    public function test_falls_back_to_stdout_when_no_file(): void
    {
        $this->assertSame('قبضت مية دولار', $this->whisper(null, " قبضت مية دولار \n")->transcribe('/tmp/a.wav', 'audio/wav'));
    }

    public function test_question_marks_are_flagged_as_encoding_failure(): void
    {
        $w = $this->whisper('?????? ?????? ?????? ??????');
        $this->assertNull($w->transcribe('/tmp/a.wav', 'audio/wav'));
        $this->assertSame('encoding', $w->failureReason);

        $this->assertTrue(WhisperCppTranscriber::looksLikeEncodingFailure('??? ?? ????? 500'));
        $this->assertFalse(WhisperCppTranscriber::looksLikeEncodingFailure('هل دفعت 500؟ نعم'));
        $this->assertFalse(WhisperCppTranscriber::looksLikeEncodingFailure('paid 20 dollars?'));
    }

    public function test_prompt_default_empty_and_non_ascii_prompt_skipped_on_windows(): void
    {
        $this->assertSame('', config('ai.voice.whisper_prompt'));
        $this->assertNotContains('--prompt', ($w = $this->whisper('نص'))->promptArgs());

        config(['ai.voice.whisper_prompt' => 'مصروف ودخل']);
        $this->assertSame(['--prompt', 'مصروف ودخل'], $this->whisper('نص', '', false)->promptArgs());
        $this->assertSame([], $this->whisper('نص', '', true)->promptArgs());

        config(['ai.voice.whisper_prompt' => 'expense income rent']);
        $this->assertSame(['--prompt', 'expense income rent'], $this->whisper('نص', '', true)->promptArgs());
    }
}
