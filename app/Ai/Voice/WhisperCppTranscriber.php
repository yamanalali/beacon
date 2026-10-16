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
    /** Arabic only (MSA + Levantine assumed, TODO: BUSINESS DECISION REQUIRED). */
    public const LANGUAGE = 'ar';

    public function name(): string
    {
        return 'whisper';
    }

    /** Set when the last transcription failed; "encoding" = text came back as "????". */
    public ?string $failureReason = null;

    public function transcribe(string $path, string $mimeType): ?string
    {
        $this->failureReason = null;
        // whisper.cpp writes <base>.txt as UTF-8; reading the file avoids console
        // code-page mangling of stdout on Windows (Arabic -> "????").
        $base = tempnam(sys_get_temp_dir(), 'stt_');
        $txt = $base.'.txt';
        try {
            $p = $this->makeProcess([
                (string) config('ai.voice.whisper_path', 'whisper-cli'),
                '-m', (string) config('ai.voice.whisper_model'),
                '-f', $path,
                '-l', self::LANGUAGE, // fixed: never auto-detect
                '-nt', '-np',
                '-otxt', '-of', $base,
                ...$this->promptArgs(),
            ]);
            $p->setTimeout((float) config('ai.voice.timeout', 60));
            $p->run();
            if (! $p->isSuccessful()) {
                Log::warning('Whisper STT failed', ['exit' => $p->getExitCode(), 'error' => mb_substr($p->getErrorOutput(), 0, 300)]);
                $this->failureReason = 'failed';

                return null;
            }
            $raw = is_file($txt) ? (string) file_get_contents($txt) : $p->getOutput();
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            if (! mb_check_encoding($raw, 'UTF-8')) {
                $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
            }
            $text = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
            if ($text === '') {
                $this->failureReason = 'empty';

                return null;
            }
            if (self::looksLikeEncodingFailure($text)) {
                Log::warning('Whisper STT returned "?" characters: text encoding failed (check UTF-8 output / prompt).');
                $this->failureReason = 'encoding';

                return null;
            }

            return $text;
        } catch (Throwable $e) {
            Log::warning('Whisper STT unavailable', ['error' => $e->getMessage()]);
            $this->failureReason = 'failed';

            return null;
        } finally {
            foreach ([$base, $txt] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
        }
    }

    /** Mostly "?" (or U+FFFD) instead of letters = the text was mangled by a code page. */
    public static function looksLikeEncodingFailure(string $text): bool
    {
        $chars = preg_split('//u', preg_replace('/[\s\p{P}\d]+/u', '', str_replace(['?', "\u{FFFD}"], '§', $text)) ?? '', -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === []) {
            return str_contains($text, '?');
        }
        $bad = count(array_filter($chars, fn ($c) => $c === '§'));

        return $bad / count($chars) >= 0.5;
    }

    /** @return list<string> */
    public function promptArgs(): array
    {
        $prompt = config('ai.voice.whisper_prompt');
        if (! is_string($prompt) || trim($prompt) === '') {
            return [];
        }
        // Windows passes CLI args through the ANSI code page: non-ASCII becomes "?".
        if ($this->isWindows() && preg_match('/[^\x20-\x7E]/', $prompt)) {
            Log::warning('WHISPER_PROMPT skipped on Windows: non-ASCII text cannot be passed safely as a CLI argument.');

            return [];
        }

        return ['--prompt', $prompt];
    }

    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /** Seam for tests. */
    protected function makeProcess(array $command): Process
    {
        return new Process($command);
    }
}
