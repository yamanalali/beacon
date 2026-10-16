<?php

/*
 * Admin multi-factor authentication (section 53).
 *
 * Entirely off by default: with BEACON_ADMIN_MFA=false login behaves exactly as before
 * and the enrollment/challenge routes return 404.
 */
return [
    'enabled' => (bool) env('BEACON_ADMIN_MFA', false),

    // TODO: BUSINESS DECISION REQUIRED - which roles must (or may) enroll. Current default: both admin roles may enroll; enrollment is not forced.
    'roles' => ['beacon_admin', 'technical_admin'],

    // TODO: BUSINESS DECISION REQUIRED - whether enrollment is mandatory for the roles above and the grace period before it is enforced. null = never enforced.
    'enforce_after_days' => null,

    // TODO: BUSINESS DECISION REQUIRED - approved factor types. Only TOTP (RFC 6238 authenticator apps) is implemented; no SMS, email or WebAuthn.
    'factors' => ['totp'],

    'issuer' => env('BEACON_ADMIN_MFA_ISSUER', env('APP_NAME', 'Beacon')),

    // Accepted clock drift in 30-second steps on either side.
    'window' => 1,

    'recovery_codes' => 8,

    // Minutes a password-verified login may wait on the second-factor challenge.
    'challenge_ttl_minutes' => 5,

    // TODO: BUSINESS DECISION REQUIRED - lost-device recovery process (who may reset another admin's MFA, identity checks, audit). Today: recovery codes only; no admin reset.
];
