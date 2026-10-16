<?php

/*
 * Configuration for Beacon sections 20-31 (compliance, readiness, MFI matching, consent, referrals).
 * Values here are placeholders until Beacon approves them. Nothing in this file is a business rule.
 */
return [
    // TODO: BUSINESS DECISION REQUIRED - Beacon must approve consent wording and versions (section 30).
    'consent' => [
        'version' => env('BEACON_CONSENT_VERSION', 'pending-beacon-approval'),
        'text_ar' => env('BEACON_CONSENT_TEXT_AR', 'نص الموافقة بانتظار اعتماد بيكون. أوافق على مشاركة المعلومات المذكورة أعلاه مع المؤسسة المحددة للغرض المذكور.'),
    ],

    // TODO: BUSINESS DECISION REQUIRED - Beacon must approve the exact data included in each referral package (section 30).
    // Keys are the only items that can be selected for disclosure; content per key is built in ReferralPackageBuilder.
    'disclosable_items' => [
        'enterprise_profile' => 'الملف التعريفي للمنشأة',
        'readiness_assessment' => 'نتيجة تقييم الجاهزية',
        'compliance_status' => 'حالة الامتثال',
    ],

    // TODO: BUSINESS DECISION REQUIRED - how many days before a deadline counts as "approaching" (section 22). NULL = show all future deadlines.
    'deadline_window_days' => env('BEACON_DEADLINE_WINDOW_DAYS') !== null ? (int) env('BEACON_DEADLINE_WINDOW_DAYS') : null,
];
