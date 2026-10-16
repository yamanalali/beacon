<?php

/*
 * Compliance-deadline reminders (rules §43).
 *
 * TODO: BUSINESS DECISION REQUIRED — notification channels and preferences.
 * Only the in-app "database" channel is implemented. Options: email, SMS,
 * WhatsApp, push; each needs approved wording, provider and opt-in rules.
 *
 * TODO: BUSINESS DECISION REQUIRED — how many days before a due date to
 * remind, and whether to repeat. Interim: one reminder per record, per due
 * date, per user, LEAD_DAYS before (default 7, configurable).
 *
 * TODO: BUSINESS DECISION REQUIRED — whether assigned Beacon advisors also
 * receive SME deadline reminders. Interim default: no (SME members only).
 */
return [
    'compliance' => [
        'lead_days' => (int) env('REMINDER_COMPLIANCE_LEAD_DAYS', 7),
        'notify_assigned_advisors' => (bool) env('REMINDER_NOTIFY_ADVISORS', false),
    ],
];
