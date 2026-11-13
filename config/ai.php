<?php

/*
 * AI provider driver. Default remains "null" until Beacon approves a vendor DPA
 * (rules §0, §36, §54). Supported drivers: null | fake | openrouter | openai.
 * Adapters are replaceable without rewriting business modules (rules §71).
 * Production still requires BEACON_DPA_*_APPROVED + allowed_data before any outbound call.
 *
 * Low-cost OpenAI defaults (voice structuring + receipt vision):
 * - gpt-6-luna for chat/vision/voice-text structuring (detail=low + reasoning_effort=none)
 * - Preferred voice path: browser Web Speech (free) then Luna structures the transcript
 * - Optional legacy audio STT: gpt-4o-mini-transcribe (not used by the normal UI)
 */
// Empty values in .env (e.g. `AI_OCR_DRIVER=` copied from .env.example) mean
// "not set": fall back to the default instead of using ''. Explicit "false" stays false.
$env = static function (string $key, mixed $default = null): mixed {
    $value = env($key);

    return $value === null || $value === '' ? $default : $value;
};

return [
    'driver' => $env('AI_DRIVER', 'null'),

    /*
     * OpenRouter (https://openrouter.ai) — set AI_DRIVER=openrouter and
     * OPENROUTER_API_KEY. Defaults use :free model slugs (no token cost;
     * OpenRouter still applies free-tier request rate limits).
     * Voice STT defaults to chat+audio on a free multimodal model because
     * dedicated Whisper transcription on OpenRouter is paid.
     * TODO: BUSINESS DECISION REQUIRED — final production model choices,
     * retention/training terms, and cost limits with Beacon.
     */
    'openrouter' => [
        'api_key' => (string) $env('OPENROUTER_API_KEY', ''),
        'base_url' => (string) $env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        // Free text chat (assistant, categorization, insights).
        'chat_model' => (string) $env('OPENROUTER_CHAT_MODEL', 'nvidia/nemotron-3.5-lightning:free'),
        // Free vision (receipt image extraction when AI_OCR_DRIVER=ai).
        'vision_model' => (string) $env('OPENROUTER_VISION_MODEL', 'google/gemma-4-26b-a4b-it:free'),
        // Free multimodal audio model used when transcription_mode=chat.
        'transcription_model' => (string) $env('OPENROUTER_TRANSCRIPTION_MODEL', 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free'),
        // chat = free multimodal audio via /chat/completions; audio_api = paid /audio/transcriptions (Whisper).
        'transcription_mode' => (string) $env('OPENROUTER_TRANSCRIPTION_MODE', 'chat'),
        'timeout' => (int) $env('OPENROUTER_TIMEOUT', 90),
        'http_referer' => $env('OPENROUTER_HTTP_REFERER', env('APP_URL')),
        'app_title' => (string) $env('OPENROUTER_APP_TITLE', env('APP_NAME', 'Beacon')),
        // Windows PHP often has empty curl.cainfo; ship/use a CA bundle so HTTPS works.
        // Empty = auto-use storage/certs/cacert.pem when present, else system default.
        'ca_bundle' => $env('OPENROUTER_CA_BUNDLE', is_file(storage_path('certs/cacert.pem')) ? storage_path('certs/cacert.pem') : null),
    ],

    /*
     * Official OpenAI API — set AI_DRIVER=openai and OPENAI_API_KEY.
     * Defaults favour the cheapest suitable models for Arabic SME voice notes
     * and receipt photos. Tighten max_tokens / image size further if needed.
     */
    'openai' => [
        'api_key' => (string) $env('OPENAI_API_KEY', ''),
        'base_url' => (string) $env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'chat_model' => (string) $env('OPENAI_CHAT_MODEL', 'gpt-6-luna'),
        'vision_model' => (string) $env('OPENAI_VISION_MODEL', 'gpt-6-luna'),
        // Luna has no /audio/transcriptions; keep the cheap dedicated STT model.
        'transcription_model' => (string) $env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
        'timeout' => (int) $env('OPENAI_TIMEOUT', 60),
        // Vision cost knobs: smaller edge + low detail + JPEG quality.
        'vision_max_edge' => (int) $env('OPENAI_VISION_MAX_EDGE', 1280),
        'vision_jpeg_quality' => (int) $env('OPENAI_VISION_JPEG_QUALITY', 72),
        'vision_detail' => (string) $env('OPENAI_VISION_DETAIL', 'low'),
        'max_tokens_chat' => (int) $env('OPENAI_MAX_TOKENS_CHAT', 400),
        'max_tokens_vision' => (int) $env('OPENAI_MAX_TOKENS_VISION', 220),
        'ca_bundle' => $env('OPENAI_CA_BUNDLE', is_file(storage_path('certs/cacert.pem')) ? storage_path('certs/cacert.pem') : null),
    ],

    // Max assistant questions per user per minute (rules beacon-platform §75).
    // TODO: BUSINESS DECISION REQUIRED — approved rate/cost limit.
    'assistant_rate_per_minute' => (int) $env('AI_ASSISTANT_RATE', 8),
    // Receipt OCR / extraction calls (AI path). Keep low to control spend.
    'ocr_rate_per_minute' => (int) $env('AI_OCR_RATE', 6),
    // Voice draft uploads that hit the provider STT path.
    'voice_rate_per_minute' => (int) $env('AI_VOICE_RATE', 6),

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
        // Fix common whisper mis-hearings of money words («الفا» -> «ألف», «همس مياه» -> «خمس مية»).
        'whisper_correct' => (bool) $env('WHISPER_CORRECT', true),
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
        // Extra per-line OCR pass (--psm 7 per text band) used for line items and the shop header.
        'line_pass' => (bool) $env('AI_OCR_LINE_PASS', true),
        // Ruled-table invoices (البيان | الكمية | السعر | القيمة): cell-by-cell OCR with a digits whitelist for numbers.
        'table_pass' => (bool) $env('AI_OCR_TABLE_PASS', true),
        'line_pass_max_lines' => (int) $env('AI_OCR_LINE_PASS_MAX_LINES', 60),
    ],

    // Review form: warn (never block) when an OCR/voice suggested date is in the future or older than this.
    // TODO: BUSINESS DECISION REQUIRED - approved threshold for an implausibly old date (default 365 days).
    'review' => [
        'old_date_days' => (int) $env('AI_REVIEW_OLD_DATE_DAYS', 365),
    ],
];
