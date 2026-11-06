<?php

/*
 * Beacon sections 53, 54 and 56. Placeholders only; nothing here is an approved business rule.
 */
return [
    // Section 53: encrypted transmission. Production defaults on; set BEACON_FORCE_HTTPS=false only for emergency local TLS bypass.
    'force_https' => filter_var(
        env('BEACON_FORCE_HTTPS', env('APP_ENV') === 'production' ? true : false),
        FILTER_VALIDATE_BOOLEAN
    ),
    'hsts_max_age' => (int) env('BEACON_HSTS_MAX_AGE', 31536000),

    // Section 56: daily backups must be configurable.
    'backup' => [
        'enabled' => (bool) env('BEACON_BACKUP_ENABLED', false),
        'time' => env('BEACON_BACKUP_TIME', '02:00'),
        'disk' => env('BEACON_BACKUP_DISK', 'local'), // private disk (storage/app/private)
        'path' => env('BEACON_BACKUP_PATH', 'backups'),
        'include_documents' => true,
        // Optional dedicated connection with CREATE/DROP DATABASE privileges for isolated restore tests.
        'restore_connection' => env('BEACON_BACKUP_RESTORE_CONNECTION'),
        'native' => [
            'mysql_dump' => env('BEACON_MYSQLDUMP_BINARY', 'mysqldump'),
            'mariadb_dump' => env('BEACON_MARIADB_DUMP_BINARY', 'mariadb-dump'),
            'mysql_client' => env('BEACON_MYSQL_BINARY', 'mysql'),
            'pgsql_dump' => env('BEACON_PG_DUMP_BINARY', 'pg_dump'),
            'pgsql_restore' => env('BEACON_PG_RESTORE_BINARY', 'pg_restore'),
            'timeout' => (int) env('BEACON_BACKUP_COMMAND_TIMEOUT', 300),
        ],
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
            // Comma-separated field names, e.g. receipt_image. Empty = nothing allowed.
            'allowed_data' => array_values(array_filter(array_map('trim', explode(',', (string) env('BEACON_DPA_OCR_ALLOWED_DATA', ''))))),
        ],
        'ai_assistant' => [
            'label_ar' => 'مزوّد الذكاء الاصطناعي للمساعد والتصنيف',
            'approved' => (bool) env('BEACON_DPA_AI_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_AI_REFERENCE'),
            // e.g. transaction_type,transaction_description,counterparty,user_question,approved_knowledge
            'allowed_data' => array_values(array_filter(array_map('trim', explode(',', (string) env('BEACON_DPA_AI_ALLOWED_DATA', ''))))),
        ],
        'insights' => [
            'label_ar' => 'مزوّد الذكاء الاصطناعي للملاحظات المالية',
            'approved' => (bool) env('BEACON_DPA_INSIGHTS_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_INSIGHTS_REFERENCE'),
            // e.g. aggregate_financial_figures
            'allowed_data' => array_values(array_filter(array_map('trim', explode(',', (string) env('BEACON_DPA_INSIGHTS_ALLOWED_DATA', ''))))),
        ],
        'speech_to_text' => [
            'label_ar' => 'مزوّد تحويل الصوت إلى نص (خارجي)',
            'approved' => (bool) env('BEACON_DPA_STT_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_STT_REFERENCE'),
            // e.g. voice_recording
            'allowed_data' => array_values(array_filter(array_map('trim', explode(',', (string) env('BEACON_DPA_STT_ALLOWED_DATA', ''))))),
        ],
        'email' => [
            'label_ar' => 'مزوّد البريد الإلكتروني للإشعارات',
            'approved' => (bool) env('BEACON_DPA_EMAIL_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_EMAIL_REFERENCE'),
            'allowed_data' => array_values(array_filter(array_map('trim', explode(',', (string) env('BEACON_DPA_EMAIL_ALLOWED_DATA', ''))))),
        ],
        'cloud_backup' => [
            'label_ar' => 'تخزين النسخ الاحتياطية خارج الخادم',
            'approved' => (bool) env('BEACON_DPA_BACKUP_APPROVED', false),
            'agreement_reference' => env('BEACON_DPA_BACKUP_REFERENCE'),
            'allowed_data' => array_values(array_filter(array_map('trim', explode(',', (string) env('BEACON_DPA_BACKUP_ALLOWED_DATA', ''))))),
        ],
    ],
];
