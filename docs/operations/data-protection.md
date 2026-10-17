# Beacon - data protection, retention, export and deletion procedures (sections 53, 54)

Status: DRAFT procedures. Every period and approval below is **TODO: BUSINESS DECISION REQUIRED** until Beacon signs off.

## 1. What the application enforces today
| Requirement (s.53) | Where |
|---|---|
| Secure authentication, login rate limits, session regeneration | `AuthenticatedSessionController`, `LoginRequest`, `ArabicThrottleRequests` |
| Role-based and record-level access | Policies + controller checks per module |
| Account revocation | User deactivation (`EnsureUserIsActive`) |
| Session management | `/account/sessions` - user sees own sessions and ends all others |
| Private document storage / controlled downloads | `config/documents.php` (private disk), document controllers |
| Audit logging | `audit_logs` (append-only `AuditLog`) |
| Secret handling | Secrets only in environment variables; checklist never shows values; secrets stripped from audit context |
| Encrypted transmission | `BEACON_FORCE_HTTPS=true` -> HTTPS redirect + HSTS (`EnforceHttps`), `SESSION_SECURE_COOKIE=true` |
| Protected stored backups | Backups encrypted (AES via Laravel Encrypter), stored on private disk |
| Daily backup configuration / restore testing | See `backup-and-restore.md` |
| Administrator MFA | Implemented (TOTP + one-time recovery codes, section 53) behind `BEACON_ADMIN_MFA` (`config('mfa.enabled')`), **off by default**. Status shown on `/operations/security`. **TODO: BUSINESS DECISION REQUIRED** - whether to switch it on in production, grace period, and lost-device reset process (today recovery codes only, no admin reset). |

The technical administrator can review the current environment at `/operations/security`.

## 2. Retention (TODO: BUSINESS DECISION REQUIRED)
Beacon must approve retention periods for: enterprise records, financial records, documents/receipts, audit logs, referral packages and consents, inactive accounts, backups.
Until approved, **nothing is deleted automatically**.

## 3. Export procedure (draft)
1. Request comes from Beacon programme management (not from a technical admin alone).
2. Export is produced through in-app export functions, which apply the same access control as the UI.
3. Exports leave the platform only to recipients approved by Beacon; the action is recorded in the audit log.

## 4. Deletion procedure (draft)
1. Deletion requests are approved by Beacon (who may approve: TODO: BUSINESS DECISION REQUIRED).
2. Financial records are never hard-deleted silently; corrections follow the financial correction workflow.
3. Audit history is never modified.
4. Data already inside backups expires with the backup retention period.

## 5. Data control and third parties (s.54)
- Programme data is used only for Beacon programme purposes.
- External processors are listed in `config/operations.php` -> `data_processors`. All are **not approved** by default.
- Code that sends data to an external provider must call `App\Support\DataControl::assertCanShare($processor, $fields)`,
  which refuses unless the processor is approved and every field is in its `allowed_data` list.
- Approving a processor requires a data-processing arrangement reference (`BEACON_DPA_*_REFERENCE`).
- Register: `/operations/data-control` (technical admin and Beacon admin).
