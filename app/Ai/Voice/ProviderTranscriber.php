<?php

namespace App\Ai\Voice;

use App\Ai\Contracts\AiProvider;
use App\Ai\Support\AiDataGuard;
use Throwable;

/** External STT through the AI provider abstraction; requires DataControl approval. */
class ProviderTranscriber implements SpeechToText
{
    /** Optional reason for the last failure: credits | denied | error. */
    public ?string $failureReason = null;

    public function __construct(private readonly AiProvider $provider) {}

    public function name(): string
    {
        return 'provider';
    }

    public function transcribe(string $path, string $mimeType): ?string
    {
        $this->failureReason = null;
        if (! AiDataGuard::allows(AiDataGuard::SPEECH)) {
            $this->failureReason = 'denied';

            return null;
        }
        try {
            $text = trim($this->provider->transcribe((string) file_get_contents($path), $mimeType));

            return $text === '' ? null : mb_substr(strip_tags($text), 0, 2000);
        } catch (Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::warning('Voice STT failed: '.$e->getMessage());
            $this->failureReason = $e->getCode() === 402 || str_contains($e->getMessage(), 'credits')
                ? 'credits'
                : 'error';

            return null;
        }
    }
}
