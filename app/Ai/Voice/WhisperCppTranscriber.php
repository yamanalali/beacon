<?php

namespace App\Ai\Voice;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Free LOCAL speech-to-text using the whisper.cpp CLI. The audio is processed on
 * Beacon's server and never leaves it, so this is not a third-party processor
 * and does not call DataControl::assertCanShare (same reasoning as local OCR).
 */
class WhisperCppTranscriber implements SpeechToText
{
    public function name(): string
    {
        return 'whisper';
    }

    public function transcribe(string $path, string $mimeType): ?string
    {
        try {
            $p = $this->makeProcess([
                (string) config('ai.voice.whisper_path', 'whisper-cli'),
                '-m', (string) config('ai.voice.whisper_model'),
                '-f', $path,
                '-l', (string) config('ai.voice.language', 'ar'),
                '-nt', '-np',
            ]);
            $p->setTimeout((float) config('ai.voice.timeout', 60));
            $p->run();
            if (! $p->isSuccessful()) {
                Log::warning('Whisper STT failed', ['exit' => $p->getExitCode(), 'error' => mb_substr($p->getErrorOutput(), 0, 300)]);

                return null;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $p->getOutput()) ?? '');

            return $text === '' ? null : $text;
        } catch (Throwable $e) {
            Log::warning('Whisper STT unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Seam for tests. */
    protected function makeProcess(array $command): Process
    {
        return new Process($command);
    }
}
