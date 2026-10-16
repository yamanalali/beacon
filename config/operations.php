<?php

/*
 * Beacon sections 53, 54 and 56. Placeholders only; nothing here is an approved business rule.
 */
return [
    // Section 53: encrypted transmission. Turn on in every non-local environment that has TLS.
    'force_https' => (bool) env('BEACON_FORCE_HTTPS', false),
    'hsts_max_age' => (int) env('BEACON_HSTS_MAX_AGE', 31536000),

    // Section 56: daily backups must be configurable.
    'backup' => [
        'enabled' => (bool) env('BEACON_BACKUP_ENABLED', false),
        'time' => env('BEACON_BACKUP_TIME', '02:00'),
        'disk' => env('BEACON_BACKUP_DISK', 'local'), // private disk (storage/app/private)
        'path' => env('BEACON_BACKUP_PATH', 'backups'),
        'include_documents' => true,
        // Encryption key for backup archives. Keep it OUTSIDE the backup location. Falls back to APP_KEY.
        'key' => env('BEACON_BACKUP_KEY'),
        // TODO: BUSINESS DECISION REQUIRED - final backup retention period (section 56). NULL = never prune automatically.
        'retention_days' => env('BEACON_BACKUP_RETENTION_DAYS') !== null ? (int) env('BEACON_BACKUP_RETENTION_DAYS') : null,
        // Hours after which the last successful backup is reported as stale on the security checklist.
        'stale_after_hours' => 26,
    ],

    /*
     * Section 54: third-party data processors. Programme data may only be sent to a processor that is
     * listed here AND marked approved (data-processing arrangement in place). Default: nothing approved.
     * TODO: BUSINESS DECISION REQUIRED - which providers are approved, with which data-processing agreement, and what data each may receive.
     */
    'data_processors' => [
        'ocr' => [
            'label_ar' => 'مزوّد التعرف الضوئي على الإيصالات (OCR)',
            'approved' => (bool) env('BEACON_DPA_OCR_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_OCR_REFERENCE'),
            'allowed_data' => [],
        ],
        'ai_assistant' => [
            'label_ar' => 'مزوّد الذكاء الاصطناعي للمساعد والتصنيف',
            'approved' => (bool) env('BEACON_DPA_AI_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_AI_REFERENCE'),
            'allowed_data' => [],
        ],
        'email' => [
            'label_ar' => 'مزوّد البريد الإلكتروني للإشعارات',
            'approved' => (bool) env('BEACON_DPA_EMAIL_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_EMAIL_REFERENCE'),
            'allowed_data' => [],
        ],
        'cloud_backup' => [
            'label_ar' => 'تخزين النسخ الاحتياطية خارج الخادم',
            'approved' => (bool) env('BEACON_DPA_BACKUP_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_BACKUP_REFERENCE'),
            'allowed_data' => [],
        ],
    ],
];
