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
     * Voice draft transaction (manager-approved, OFF by default).
     * stt_driver: "whisper" = LOCAL whisper.cpp binary (audio never leaves the
     * server, no DataControl approval needed); "provider" = the AI provider
     * abstraction, guarded by DataControl processor "speech_to_text".
     * TODO: BUSINESS DECISION REQUIRED — supported languages (Arabic MSA +
     * Levantine assumed), the STT provider (local whisper assumed), and recording
     * retention (deleted immediately after transcription assumed).
     */
    'voice' => [
        'enabled' => (bool) env('AI_VOICE_ENABLED', false),
        'stt_driver' => env('AI_STT_DRIVER', 'whisper'),
        'whisper_path' => env('WHISPER_PATH', 'whisper-cli'),
        'whisper_model' => env('WHISPER_MODEL', storage_path('app/whisper/ggml-small.bin')),
        'language' => env('WHISPER_LANGUAGE', 'ar'),
        'timeout' => (int) env('WHISPER_TIMEOUT', 60),
        'max_kb' => (int) env('AI_VOICE_MAX_KB', 5120),
    ],
];
