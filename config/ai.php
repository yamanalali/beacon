<?php

/*
 * TODO: BUSINESS DECISION REQUIRED — AI provider, model, data-retention and
 * model-training terms are not approved (rules §0, §36). Options: (a) a hosted
 * LLM vendor under a no-training/zero-retention agreement, (b) a self-hosted
 * model, (c) keep AI disabled. Until decided the default driver is "null":
 * the assistant reports that AI is unavailable and offers escalation, and all
 * non-AI workflows keep working. Tests use the deterministic "fake" driver.
 */
// Empty values in .env (e.g. `AI_OCR_DRIVER=` copied from .env.example) mean
// "not set": fall back to the default instead of using ''. Explicit "false" stays false.
$env = static function (string $key, mixed $default = null): mixed {
    $value = env($key);

    return $value === null || $value === '' ? $default : $value;
};

return [
    'driver' => $env('AI_DRIVER', 'null'),

    // Max assistant questions per user per minute (rules beacon-platform §75).
    // TODO: BUSINESS DECISION REQUIRED — approved rate/cost limit.
    'assistant_rate_per_minute' => (int) $env('AI_ASSISTANT_RATE', 10),

    // Max published knowledge snippets sent as context (data minimisation §74).
    'max_context_articles' => 3,

    /*
     * Voice draft transaction (manager-approved, OFF by default).
     * stt_driver: "whisper" = LOCAL whisper.cpp binary (audio never leaves the
     * server, no DataControl approval needed); "provider" = the AI provider
     * abstraction, guarded by DataControl processor "speech_to_text".
     * TODO: BUSINESS DECISION REQUIRED — supported languages (Arabic MSA +
     * Levantine assumed), the STT provider (local whisper assumed), and recording
     * retention (deleted immediately after transcription assumed).
     */
    'voice' => [
        'enabled' => (bool) $env('AI_VOICE_ENABLED', false),
        'stt_driver' => $env('AI_STT_DRIVER', 'whisper'),
        'whisper_path' => $env('WHISPER_PATH', 'whisper-cli'),
        // WHISPER_MODEL: a .bin path, or a short name (small | medium | large-v3-turbo)
        // resolved to storage/app/whisper/ggml-<name>.bin. Empty = whisper_default_model.
        'whisper_model' => $env('WHISPER_MODEL', ''),
        // Manager decision: large-v3-turbo won the Levantine benchmark (docs/whisper-dialect-benchmark.md).
        'whisper_default_model' => 'large-v3-turbo',
        'whisper_threads' => (int) $env('WHISPER_THREADS', 0), // 0 = whisper.cpp default
        // Language is fixed to Arabic ("-l ar", no auto-detect). Optional initial prompt
        // biasing Whisper toward finance vocabulary; empty = no --prompt.
        'whisper_prompt' => env('WHISPER_PROMPT', ''), // optional; skipped on Windows if non-ASCII
        'timeout' => (int) $env('WHISPER_TIMEOUT', 120),
        'max_kb' => (int) $env('AI_VOICE_MAX_KB', 5120),
    ],

    /*
     * Receipt OCR driver (rules §15). "tesseract" = free LOCAL OCR (image never
     * leaves the server, so no DataControl approval is needed). Default: tesseract
     * in APP_ENV=local, otherwise the AI/manual binding above applies.
     * Values: tesseract | ai | manual.
     */
    'ocr' => [
        'driver' => $env('AI_OCR_DRIVER', env('APP_ENV') === 'local' ? 'tesseract' : null),
        'tesseract_path' => $env('TESSERACT_PATH', 'tesseract'),
        // Languages are always ara+eng. Folder with ara/eng(/osd).traineddata, passed as
        // --tessdata-dir (e.g. C:\\Tesseract-OCR\\tessdata); empty = flag not passed; null = Tesseract's own tessdata folder.
        'tessdata_dir' => env('TESSERACT_TESSDATA_DIR'),
        'timeout' => (int) $env('TESSERACT_TIMEOUT', 30),
        // Image clean-up before OCR (ImageMagick CLI, else GD, else skipped). On by default in local.
        'preprocess' => (bool) $env('AI_OCR_PREPROCESS', env('APP_ENV') === 'local'),
        'preprocess_osd' => (bool) $env('AI_OCR_PREPROCESS_OSD', true),
        'magick_path' => env('MAGICK_PATH'), // null = auto-detect magick/convert
        'min_width' => (int) $env('AI_OCR_MIN_WIDTH', 1200),
        'osd_min_confidence' => (float) $env('AI_OCR_OSD_MIN_CONFIDENCE', 2.0),
        'psm' => $env('TESSERACT_PSM', '6'), // 6 = single text block; best on the synthetic benchmark
    ],
];
