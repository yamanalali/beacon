<?php

/*
 * Admin multi-factor authentication (section 53).
 *
 * Local/testing: off unless BEACON_ADMIN_MFA=true.
 * Production: on by default (override with BEACON_ADMIN_MFA=false only for emergency).
 * Enrollment is enforced after enforce_after_days (production default: 7).
 *
 * TODO: BUSINESS DECISION REQUIRED - lost-device recovery process (who may reset another
 * admin's MFA, identity checks, audit). Today: recovery codes only; no admin reset.
 */

$isProduction = env('APP_ENV') === 'production';

$enforceAfter = env('BEACON_ADMIN_MFA_ENFORCE_AFTER_DAYS');
if ($enforceAfter === null) {
    $enforceAfter = $isProduction ? 7 : null;
} elseif ($enforceAfter === '') {
    $enforceAfter = null;
} else {
    $enforceAfter = (int) $enforceAfter;
}

return [
    'enabled' => filter_var(
        env('BEACON_ADMIN_MFA', $isProduction ? true : false),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Roles that may (and when enforced, must) enroll.
    'roles' => ['beacon_admin', 'technical_admin'],

    // Days after enforced_from (or user created_at) before unenrolled admins are locked to MFA setup.
    // null = never forced. 0 = immediate. Production default: 7.
    'enforce_after_days' => $enforceAfter,

    // Optional ISO date when the grace clock starts for every eligible admin. Empty = use user.created_at.
    'enforced_from' => env('BEACON_ADMIN_MFA_ENFORCED_FROM') ?: null,

    // Only TOTP (RFC 6238 authenticator apps) is implemented; no SMS, email or WebAuthn.
    'factors' => ['totp'],

    'issuer' => env('BEACON_ADMIN_MFA_ISSUER', env('APP_NAME', 'Beacon')),

    // Accepted clock drift in 30-second steps on either side.
    'window' => 1,

    'recovery_codes' => 8,

    // Minutes a password-verified login may wait on the second-factor challenge.
    'challenge_ttl_minutes' => 5,
];
