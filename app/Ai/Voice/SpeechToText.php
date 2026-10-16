<?php

namespace App\Ai\Voice;

interface SpeechToText
{
    public function name(): string;

    /** Returns the transcript, or null when speech-to-text is unavailable/failed. */
    public function transcribe(string $path, string $mimeType): ?string;
}
