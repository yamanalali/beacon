<?php

/*
 * TODO: BUSINESS DECISION REQUIRED — AI provider, model, data-retention and
 * model-training terms are not approved (rules §0, §36). Options: (a) a hosted
 * LLM vendor under a no-training/zero-retention agreement, (b) a self-hosted
 * model, (c) keep AI disabled. Until decided the default driver is "null":
 * the assistant reports that AI is unavailable and offers escalation, and all
 * non-AI workflows keep working. Tests use the deterministic "fake" driver.
 */
return [
    'driver' => env('AI_DRIVER', 'null'),

    // Max assistant questions per user per minute (rules beacon-platform §75).
    // TODO: BUSINESS DECISION REQUIRED — approved rate/cost limit.
    'assistant_rate_per_minute' => (int) env('AI_ASSISTANT_RATE', 10),

    // Max published knowledge snippets sent as context (data minimisation §74).
    'max_context_articles' => 3,

    /*
     * Receipt OCR driver (rules §15). "tesseract" = free LOCAL OCR (image never
     * leaves the server, so no DataControl approval is needed). Default: tesseract
     * in APP_ENV=local, otherwise the AI/manual binding above applies.
     * Values: tesseract | ai | manual.
     */
    'ocr' => [
        'driver' => env('AI_OCR_DRIVER', env('APP_ENV') === 'local' ? 'tesseract' : null),
        'tesseract_path' => env('TESSERACT_PATH', 'tesseract'),
        'tesseract_langs' => env('TESSERACT_LANGS', 'ara+eng'),
        'timeout' => (int) env('TESSERACT_TIMEOUT', 30),
    ],
];
