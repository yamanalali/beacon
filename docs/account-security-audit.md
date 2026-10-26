# Account security review — 2026-10-25

Reviewed source at main `b066c4cb26fcced467819bf29808434e79c72c9b` and tested the fixes on a disposable database. This is a source review and regression exercise, not a penetration test against the production deployment or a guarantee that every vulnerability is excluded.

## Reproduced issues and fixes

Six regression tests failed against the original implementation and passed after repair:

| Finding | Assessment | Result |
| --- | --- | --- |
| Changing a password left other database sessions authenticated | High | Delete other sessions, rotate current session/CSRF and remember token; audit without secrets |
| Resetting a password left existing database sessions authenticated | High | Revoke sessions and remember token; audit reset |
| A pending password-verified MFA challenge survived a password change | High | Bind challenge to current security state; lock the account through verification and session binding |
| Starting MFA enrollment needed no password confirmation | Medium | Require current password, throttle, revoke other sessions after factor changes |
| Password reset request responses disclosed registered email addresses | Medium | Same redirect, status and validation bag for registered, unknown and broker-throttled addresses |
| Legacy mixed-case emails could not authenticate using normalized email | Functional | Resolve a unique case-insensitive match; fail closed for ambiguous historical duplicates |

Additional protections and regressions cover:

- Authenticated session fingerprints include credential, role, active status, institution, permissions and factor changes. Fingerprints work independently of database session deletion; direct SQL changes to covered fields are also detected. Random security stamps prevent deactivate/reactivate from restoring a previously valid session. Remember tokens rotate with sensitive model changes.
- Administrator mutations lock real account rows in deterministic order, recheck the actor inside the transaction, and preserve the last-active-administrator guard. This replaces an aggregate query with a row lock. Invitation acceptance rechecks and locks its issuer, and checks legacy case-insensitive email collisions.
- MFA has an account-wide challenge limiter in addition to route/IP throttling. Failed-attempt auditing and limiter writes persist even with a database cache store. Codes and reset tokens are excluded from validation input flashing, and client password/OTP fields are cleared after submission.
- Authenticated and authentication pages send `Cache-Control: no-store, private` for ordinary HTTP caches. The explicit offline workspace remains supported; account/security pages remain excluded from offline caches and writes.
- Offline authorization revisions include account state, enterprise membership/advisor assignment and MFI referral disclosure/consent state. On detection of a revision change, old private pages are discarded and unsent operations are preserved for explicit review. A fresh server session validates a changed revision before accepting page responses. Unauthorized reconnects lock private caches.

## Validation

- Complete PHP suite: 626 passed; one optional Tesseract integration test skipped where the external OCR binary is unavailable.
- Frontend: 279 tests passed; TypeScript and production Vite build passed.
- Real Chromium acceptance: automatic login preparation, offline reload, persistent transaction and binary attachment, local reports/CSV, reconnect, exactly-once records, permission revocation with unsent work retained for review, and offline sign-out/server logout. No browser or PHP runtime errors.
- Composer and npm audits: no reported dependency advisories on the review date. This does not detect application logic flaws or undisclosed vulnerabilities.
- PHP formatting and `git diff --check` passed.

## Deployment and remaining boundaries

1. Deploy the migration before serving the new code: `php artisan migrate --force`; rebuild assets and refresh application/config caches through the normal deployment process. No production deployment was performed by this review.
2. Existing sessions without a security fingerprint acquire their first baseline on use after deployment. Force a one-time sign-out of pre-deployment sessions through the configured session store if immediate invalidation of all old sessions is required. New successful logins bind a fingerprint before granting workspace access.
3. MFA remains off by default and enrollment is optional in the existing product policy. Review `config/mfa.php` and provision administrator enrollment/recovery before deciding to require it. This patch does not silently change that policy.
4. Production HTTPS, reverse proxy trust, secure cookies, debug mode, mail delivery, backups, server permissions and actual stored account data were not accessible and have not been verified. Historical case-insensitive email duplicates, if any, need an administrator review; the login/reset flows refuse ambiguous matches.
5. A fully disconnected device cannot discover a remote permission revocation until it reconnects. Already downloaded data cannot be remotely erased while offline. Use trusted devices and sign out to clear the local private store. Unsent work may need review after an authorization change.
6. Lock behavior was exercised using SQLite. Production database concurrency and production CSP/HTTPS settings need environment-specific verification; code uses portable row locking rather than aggregate `FOR UPDATE`.

Framework reference: https://laravel.com/docs/12.x/authentication#invalidating-sessions-on-other-devices
