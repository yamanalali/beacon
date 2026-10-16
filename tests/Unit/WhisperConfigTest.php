<?php

namespace Tests\Unit;

use App\Ai\Voice\WhisperCppTranscriber;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WhisperConfigTest extends TestCase
{
    private string $out = 'دفعت';

    private string $text = '';

    private function run_(): array
    {
        $out = $this->out;
        $w = new class($out) extends WhisperCppTranscriber
        {
            public array $cmd = [];

            public function __construct(private string $out) {}

            protected function isWindows(): bool
            {
                return false;
            }

            protected function modelExists(string $path): bool

            {

                return true;

            }


            protected function makeProcess(array $command): Process
            {
                $this->cmd = $command;
                file_put_contents($command[array_search('-of', $command, true) + 1].'.txt', $this->out);

                return new Process(['php', '-r', '']);
            }
        };
        $this->text = (string) $w->transcribe('/tmp/a.wav', 'audio/wav');

        return $w->cmd;
    }

    private function arg(array $cmd, string $flag): ?string
    {
        $i = array_search($flag, $cmd, true);

        return $i === false ? null : $cmd[$i + 1];
    }

    public function test_empty_model_uses_large_v3_turbo_default(): void
    {
        config(['ai.voice.whisper_model' => '', 'ai.voice.whisper_default_model' => 'large-v3-turbo']);
        $this->assertSame(storage_path('app/whisper/ggml-large-v3-turbo.bin'), $this->arg($this->run_(), '-m'));
    }

    public function test_short_model_name_resolves_to_storage(): void
    {
        config(['ai.voice.whisper_model' => 'small']);
        $this->assertSame(storage_path('app/whisper/ggml-small.bin'), $this->arg($this->run_(), '-m'));
    }

    public function test_explicit_path_is_used_as_is(): void
    {
        config(['ai.voice.whisper_model' => 'C:\\models\\ggml-medium.bin']);
        $this->assertSame('C:\\models\\ggml-medium.bin', $this->arg($this->run_(), '-m'));
        config(['ai.voice.whisper_model' => '/opt/w/ggml-medium.bin']);
        $this->assertSame('/opt/w/ggml-medium.bin', $this->arg($this->run_(), '-m'));
    }

    public function test_language_is_always_arabic(): void
    {
        config(['ai.voice.whisper_model' => 'small']);
        $this->assertSame('ar', $this->arg($this->run_(), '-l'));
    }

    public function test_prompt_and_threads_from_config(): void
    {
        config(['ai.voice.whisper_model' => 'small', 'ai.voice.whisper_prompt' => 'دفعت، قبضت', 'ai.voice.whisper_threads' => 4]);
        $cmd = $this->run_();
        $this->assertSame('دفعت، قبضت', $this->arg($cmd, '--prompt'));
        $this->assertSame('4', $this->arg($cmd, '-t'));
    }

    public function test_empty_prompt_and_zero_threads_add_no_flags(): void
    {
        config(['ai.voice.whisper_model' => 'small', 'ai.voice.whisper_prompt' => '', 'ai.voice.whisper_threads' => 0]);
        $cmd = $this->run_();
        $this->assertNull($this->arg($cmd, '--prompt'));
        $this->assertNotContains('-t', $cmd);
    }

    public function test_money_correction_applied_and_can_be_disabled(): void
    {
        config(['ai.voice.whisper_model' => 'small', 'ai.voice.whisper_correct' => true]);
        $this->out = 'دفعت همس مياه الفا';
        $this->run_();
        $this->assertSame('دفعت خمس مية ألف', $this->text);
        config(['ai.voice.whisper_correct' => false]);
        $this->run_();
        $this->assertSame('دفعت همس مياه الفا', $this->text);
    }

    public function test_missing_model_file_fails_without_running_whisper(): void
    {
        config(['ai.voice.whisper_model' => '/nonexistent/ggml-x.bin']);
        $w = new WhisperCppTranscriber;
        $this->assertNull($w->transcribe('/tmp/a.wav', 'audio/wav'));
        $this->assertSame('failed', $w->failureReason);
    }
}
