# Tenant isolation audit (`fix/tenant-isolation-audit`, base `4efff95`)

Scope: transactions, transaction_items, receipts/documents/files (incl. download/preview), receipt extractions, voice drafts, readiness assessments (+ snapshots, recalculate, results), referrals / referral packages, MFI matching, dashboards, advisory, AI escalations, notifications. For QA sections 55, 57, 58, 59.

## Method

- Read every route file, controller action, policy, FormRequest `authorize()` and `HandleInertiaRequests` shared props.
- `tests/Feature/Authorization/TenantIsolationMatrixTest.php` calls **every** route that binds `{enterprise}`, `{transaction}`, `{document}`, `{extraction}`, `{assessment}`, `{referral}`, `{followUp}`, `{escalation}` using enterprise B's ids, as (1) SME of A, (2) advisor assigned only to A, (3) MFI user of another institution, and requires 403/404. Runs against the live route table, so new routes are covered automatically.
- Body ids checked for scoping: `transaction` on document upload (scoped ✓), `readiness_assessment_id` on referral (scoped to enterprise + finalized ✓), `advisory_activity_id` on follow-up (scoped ✓), `enterprise` on AI ask/escalate (`can('view')` ✓), result `person` ids (members+advisors ✓), `responsible_user_id` (**was not scoped**, fixed).
- Inertia shared props: `auth.user` (name/role), `auth.can`, `auth.quick_record` (own enterprise only), `auth.navigation` — no other tenant's data.

## Holes found and fixed

| # | Endpoint | Problem | Fix | Test |
|---|---|---|---|---|
| H1 | `POST /enterprises/{enterprise}/follow-ups`, `POST /enterprises/{enterprise}/advisory` | `responsible_user_id` only `exists:users,id`: an advisor could name any user (another enterprise's SME, an MFI user) as responsible. | `ScopesResponsibleUser`: must be a member or assigned advisor of this enterprise (422 otherwise). `TODO: BUSINESS DECISION REQUIRED` for unassigned admins. | `TenantIsolationHolesTest::test_hole1_*` |
| H2 | `POST /enterprises/{enterprise}/documents` | Policy check ran in the controller **after** FormRequest validation, so another tenant got 302 + validation errors (probe/oracle) instead of 403. | `StoreDocumentRequest::authorize()` uses the same `upload` policy. | `test_hole2_document_upload_to_another_enterprise_is_403_before_validation` |

## Reported only (not changed)

- Off-limits (another dev): `admin/readiness/*`, `ReadinessAssessmentResult` update. Reviewed: `results.update` checks `canManageEnterprise` and `result->readiness_assessment_id === assessment->id` (404), so no cross-tenant hole found. `{result}` is bound by numeric id (not public_id) — suggest `public_id` or `scopeBindings()`.
- Voice parser / OCR / transcription: not changed. `ai.voice-drafts.store` itself is enterprise-authorized (matrix ✓).
- `local` disk has `serve => true`: `/storage/{path}` only serves with a valid temporary signature; documents are downloaded through `documents.download` (policy ✓).
- Advisory/follow-up FormRequests also validate before the action's own `canRecord`, but their `authorize()` already enforces `update` on the enterprise, so no oracle.

## Endpoints checked

| Method | URI | Name | Guard / policy | Result |
|---|---|---|---|---|
| `GET` | `//` | `home` | auth | OK |
| `GET` | `/account` | `account.show` | auth, EnsureUserIsActive | OK |
| `PUT` | `/account/password` | `account.password` | auth, EnsureUserIsActive, UpdatePasswordRequest | OK |
| `GET` | `/account/sessions` | `account.sessions` | auth | OK |
| `DELETE` | `/account/sessions/others` | `account.sessions.destroy-others` | auth | OK |
| `GET` | `/account/two-factor` | `two-factor.show` | auth, EnsureUserIsActive, EnsureAdminMfaAvailable | OK |
| `POST` | `/account/two-factor` | `two-factor.enable` | auth, EnsureUserIsActive, EnsureAdminMfaAvailable | OK |
| `DELETE` | `/account/two-factor` | `two-factor.disable` | auth, EnsureUserIsActive, EnsureAdminMfaAvailable, ThrottleRequests:5,1 | OK |
| `POST` | `/account/two-factor/confirm` | `two-factor.confirm` | auth, EnsureUserIsActive, EnsureAdminMfaAvailable, ThrottleRequests:5,1 | OK |
| `POST` | `/account/two-factor/recovery-codes` | `two-factor.recovery-codes` | auth, EnsureUserIsActive, EnsureAdminMfaAvailable, ThrottleRequests:5,1 | OK |
| `GET` | `/admin` | `admin.index` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin | OK |
| `GET` | `/admin/audit-logs` | `admin.audit-logs.index` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, viewAny | OK |
| `GET` | `/admin/audit-logs/export` | `admin.audit-logs.export` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, ThrottleRequests:10,1, viewAny | OK |
| `GET` | `/admin/audit-logs/{auditLog}` | `admin.audit-logs.show` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, view | OK |
| `GET` | `/admin/compliance` | `admin.compliance.index` | auth | OK |
| `POST` | `/admin/compliance` | `admin.compliance.store` | auth | OK |
| `PUT` | `/admin/compliance/versions/{version}` | `admin.compliance.versions.update` | auth | OK |
| `POST` | `/admin/compliance/versions/{version}/publish` | `admin.compliance.versions.publish` | auth | OK |
| `POST` | `/admin/compliance/{requirement}/deactivate` | `admin.compliance.deactivate` | auth | OK |
| `POST` | `/admin/compliance/{requirement}/replace` | `admin.compliance.replace` | auth | OK |
| `POST` | `/admin/compliance/{requirement}/versions` | `admin.compliance.versions.store` | auth | OK |
| `GET` | `/admin/mfis` | `admin.mfis.index` | auth | OK |
| `POST` | `/admin/mfis` | `admin.mfis.store` | auth | OK |
| `POST` | `/admin/mfis/criteria/{criterion}/deactivate` | `admin.mfis.criteria.deactivate` | auth | OK |
| `PUT` | `/admin/mfis/{institution}` | `admin.mfis.update` | auth | OK |
| `POST` | `/admin/mfis/{institution}/criteria` | `admin.mfis.criteria.store` | auth | OK |
| `POST` | `/admin/mfis/{institution}/users` | `admin.mfis.users.attach` | auth | OK |
| `GET` | `/admin/readiness` | `admin.readiness.index` | auth | OK (off-limits area: reviewed, not changed) |
| `POST` | `/admin/readiness` | `admin.readiness.store` | auth | OK (off-limits area: reviewed, not changed) |
| `DELETE` | `/admin/readiness/criteria/{criterion}` | `admin.readiness.criteria.destroy` | auth | OK (off-limits area: reviewed, not changed) |
| `POST` | `/admin/readiness/{ruleSet}/criteria` | `admin.readiness.criteria.store` | auth | OK (off-limits area: reviewed, not changed) |
| `POST` | `/admin/readiness/{ruleSet}/publish` | `admin.readiness.publish` | auth | OK (off-limits area: reviewed, not changed) |
| `GET` | `/ai/admin/knowledge` | `ai.knowledge.index` | auth, EnsureUserHasRole:beacon_admin, manage | OK |
| `POST` | `/ai/admin/knowledge` | `ai.knowledge.store` | auth, EnsureUserHasRole:beacon_admin, manage | OK |
| `GET` | `/ai/admin/knowledge/page` | `ai.pages.knowledge.index` | auth, EnsureUserHasRole:beacon_admin, manage | OK |
| `PUT` | `/ai/admin/knowledge/{article}` | `ai.knowledge.update` | auth, EnsureUserHasRole:beacon_admin, manage | OK |
| `POST` | `/ai/admin/knowledge/{article}/archive` | `ai.knowledge.archive` | auth, EnsureUserHasRole:beacon_admin | OK |
| `POST` | `/ai/admin/knowledge/{article}/publish` | `ai.knowledge.publish` | auth, EnsureUserHasRole:beacon_admin | OK |
| `POST` | `/ai/assistant/ask` | `ai.assistant.ask` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, ThrottleRequests:ai-assistant | OK |
| `GET` | `/ai/enterprises/{enterprise}/insights` | `ai.finance.insights` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, ThrottleRequests:ai-insights, reports.view | OK |
| `GET` | `/ai/enterprises/{enterprise}/review-flags` | `ai.finance.flags` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, reports.view | OK |
| `POST` | `/ai/enterprises/{enterprise}/voice-drafts` | `ai.voice-drafts.store` | auth, ThrottleRequests:10,1, create | OK |
| `POST` | `/ai/escalations` | `ai.escalations.store` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, ThrottleRequests:ai-assistant | OK |
| `GET` | `/ai/escalations` | `ai.escalations.index` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin | OK |
| `GET` | `/ai/escalations/{escalation}` | `ai.escalations.show` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, view | OK |
| `GET` | `/ai/knowledge/published` | `ai.knowledge.published` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, readPublished | OK |
| `GET` | `/ai/pages/assistant` | `ai.pages.assistant` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin | OK |
| `GET` | `/ai/pages/escalations` | `ai.pages.escalations.index` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin | OK |
| `GET` | `/ai/pages/escalations/{escalation}` | `ai.pages.escalations.show` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, view | OK |
| `GET` | `/ai/pages/knowledge` | `ai.pages.knowledge.published` | auth, EnsureUserHasRole:sme_user,beacon_advisor,beacon_admin, readPublished, manage | OK |
| `GET` | `/dashboard/beacon` | `dashboard.beacon` | auth | OK |
| `GET` | `/dashboard/sme` | `dashboard.sme` | auth | OK |
| `GET` | `/documents` | `documents.entry` | auth, viewAny | OK |
| `GET` | `/documents/{document}` | `documents.show` | auth, view, update, archive | OK |
| `PUT` | `/documents/{document}` | `documents.update` | auth, update | OK |
| `POST` | `/documents/{document}/archive` | `documents.archive` | auth, archive | OK |
| `GET` | `/documents/{document}/download` | `documents.download` | auth, view | OK |
| `POST` | `/documents/{document}/extractions` | `documents.extractions.store` | auth, ThrottleRequests:20,1 | OK |
| `GET` | `/documents/{document}/preview` | `documents.preview` | auth, view | OK |
| `GET` | `/enterprises` | `enterprises.index` | auth, viewAny | OK |
| `POST` | `/enterprises` | `enterprises.store` | auth, StoreEnterpriseRequest | OK |
| `GET` | `/enterprises/create` | `enterprises.create` | auth, create | OK |
| `GET` | `/enterprises/{enterprise}` | `enterprises.show` | auth, view | OK |
| `PUT` | `/enterprises/{enterprise}` | `enterprises.update` | auth, UpdateEnterpriseProfileRequest | OK |
| `POST` | `/enterprises/{enterprise}/advisors` | `enterprises.advisors.store` | auth, AssignEnterpriseUserRequest | OK |
| `GET` | `/enterprises/{enterprise}/advisory` | `enterprises.advisory.index` | auth, view, update | OK |
| `POST` | `/enterprises/{enterprise}/advisory` | `enterprises.advisory.store` | auth, StoreAdvisoryActivityRequest | FIXED (H1) |
| `GET` | `/enterprises/{enterprise}/compliance` | `enterprises.compliance.index` | auth, ReadinessAccess::canViewEnterprise, ReadinessAccess::canManageEnterprise | OK |
| `PUT` | `/enterprises/{enterprise}/compliance/{requirement}` | `enterprises.compliance.update` | auth, ReadinessAccess::canManageEnterprise | OK |
| `GET` | `/enterprises/{enterprise}/documents` | `enterprises.documents.index` | auth, viewAny, upload, create | OK |
| `POST` | `/enterprises/{enterprise}/documents` | `enterprises.documents.store` | auth, ThrottleRequests:30,1, StoreDocumentRequest, upload | FIXED (H2) |
| `GET` | `/enterprises/{enterprise}/edit` | `enterprises.edit` | auth, update | OK |
| `GET` | `/enterprises/{enterprise}/extractions/pending` | `enterprises.extractions.pending` | auth, view | OK |
| `POST` | `/enterprises/{enterprise}/follow-ups` | `enterprises.follow-ups.store` | auth, StoreFollowUpActionRequest | FIXED (H1) |
| `POST` | `/enterprises/{enterprise}/members` | `enterprises.members.store` | auth, AssignEnterpriseUserRequest | OK |
| `GET` | `/enterprises/{enterprise}/mfi-matching` | `enterprises.mfi-matching` | auth, ReadinessAccess::canManageEnterprise | OK |
| `GET` | `/enterprises/{enterprise}/readiness` | `enterprises.readiness.index` | auth, ReadinessAccess::canViewEnterprise, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/enterprises/{enterprise}/readiness` | `enterprises.readiness.store` | auth, ReadinessAccess::canManageEnterprise | OK |
| `GET` | `/enterprises/{enterprise}/referrals` | `enterprises.referrals.index` | auth, ReadinessAccess::canViewEnterprise, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/enterprises/{enterprise}/referrals` | `enterprises.referrals.store` | auth, ReadinessAccess::canManageEnterprise | OK |
| `GET` | `/enterprises/{enterprise}/reports` | `enterprises.reports.show` | auth, reports.view | OK |
| `GET` | `/enterprises/{enterprise}/reports/export/{format}` | `enterprises.reports.export` | auth, ThrottleRequests:30,1, reports.export | OK |
| `GET` | `/enterprises/{enterprise}/transactions` | `enterprises.transactions.index` | auth, viewAny, create | OK |
| `POST` | `/enterprises/{enterprise}/transactions` | `enterprises.transactions.store` | auth, StoreTransactionRequest, create | OK |
| `GET` | `/enterprises/{enterprise}/transactions/create` | `enterprises.transactions.create` | auth, create | OK |
| `GET` | `/extractions/{extraction}` | `extractions.show` | auth, view, create | OK |
| `POST` | `/extractions/{extraction}/confirm` | `extractions.confirm` | auth, StoreTransactionRequest, create | OK |
| `POST` | `/extractions/{extraction}/reject` | `extractions.reject` | auth, create | OK |
| `POST` | `/follow-ups/{followUp}/complete` | `follow-ups.complete` | auth, view | OK |
| `GET` | `/forgot-password` | `password.request` | auth, RedirectIfAuthenticated | OK |
| `POST` | `/forgot-password` | `password.email` | auth, RedirectIfAuthenticated, PasswordResetLinkRequest | OK |
| `GET` | `/login` | `login` | auth, RedirectIfAuthenticated | OK |
| `POST` | `/login` | `` | auth, RedirectIfAuthenticated, ThrottleRequests:5,1, LoginRequest | OK |
| `POST` | `/logout` | `logout` | auth | OK |
| `GET` | `/mfi/referrals` | `mfi.referrals.index` | auth, PageAccess::MFI_REFERRAL_INBOX, ReadinessAccess::mfiCanViewReferral | OK |
| `GET` | `/notifications` | `notifications.index` | auth | OK |
| `GET` | `/notifications/page` | `notifications.page` | auth | OK |
| `POST` | `/notifications/{notification}/read` | `notifications.read` | auth | OK |
| `GET` | `/operations/backups` | `operations.backups` | auth | OK |
| `POST` | `/operations/backups` | `operations.backups.run` | auth, ThrottleRequests:6,1 | OK |
| `POST` | `/operations/backups/{backupRun}/restore-test` | `operations.backups.restore-test` | auth, ThrottleRequests:6,1 | OK |
| `GET` | `/operations/data-control` | `operations.data-control` | auth, PageAccess::OPERATIONS_DATA_CONTROL | OK |
| `GET` | `/operations/security` | `operations.security` | auth | OK |
| `GET` | `/readiness-assessments/{assessment}` | `readiness-assessments.show` | auth, ReadinessAccess::canViewEnterprise, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/readiness-assessments/{assessment}/finalize` | `readiness-assessments.finalize` | auth, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/readiness-assessments/{assessment}/recalculate` | `readiness-assessments.recalculate` | auth, ReadinessAccess::canManageEnterprise | OK |
| `PUT` | `/readiness-assessments/{assessment}/results/{result}` | `readiness-assessments.results.update` | auth, ReadinessAccess::canManageEnterprise | OK (off-limits area: reviewed, not changed) |
| `GET` | `/referrals/{referral}` | `referrals.show` | auth, ReadinessAccess::canViewReferral, ReadinessAccess::isEnterpriseMember, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/referrals/{referral}/consent` | `referrals.consent` | auth, ReadinessAccess::isEnterpriseMember | OK |
| `POST` | `/referrals/{referral}/consent/withdraw` | `referrals.consent.withdraw` | auth, ReadinessAccess::isEnterpriseMember | OK |
| `POST` | `/referrals/{referral}/outcome` | `referrals.outcome` | auth, ReadinessAccess::mfiCanViewReferral, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/referrals/{referral}/share` | `referrals.share` | auth, ReadinessAccess::canManageEnterprise | OK |
| `POST` | `/referrals/{referral}/status` | `referrals.status` | auth, ReadinessAccess::mfiCanViewReferral | OK |
| `GET` | `/reports` | `reports.entry` | auth, viewAny | OK |
| `POST` | `/reset-password` | `password.store` | auth, RedirectIfAuthenticated, NewPasswordRequest | OK |
| `GET` | `/reset-password/{token}` | `password.reset` | auth, RedirectIfAuthenticated | OK |
| `GET` | `/storage/{path}` | `storage.local` | Laravel signed-URL serve (private disk) | OK (signature required) |
| `PUT` | `/storage/{path}` | `storage.local` | Laravel signed-URL serve (private disk) | OK (signature required) |
| `GET` | `/transactions` | `transactions.entry` | auth, viewAny | OK |
| `GET` | `/transactions/{transaction}` | `transactions.show` | auth, view, correct, settle, upload | OK |
| `GET` | `/transactions/{transaction}/correct` | `transactions.correct.edit` | auth, correct | OK |
| `POST` | `/transactions/{transaction}/correct` | `transactions.correct` | auth, CorrectTransactionRequest, correct | OK |
| `POST` | `/transactions/{transaction}/settlements` | `transactions.settlements.store` | auth, StoreSettlementRequest, settle | OK |
| `GET` | `/two-factor-challenge` | `two-factor.challenge` | auth, RedirectIfAuthenticated, EnsureMfaEnabled | OK |
| `POST` | `/two-factor-challenge` | `` | auth, RedirectIfAuthenticated, EnsureMfaEnabled, ThrottleRequests:5,1 | OK |
| `GET` | `/users` | `users.index` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin | OK |
| `POST` | `/users` | `users.store` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, StoreUserRequest | OK |
| `GET` | `/users/create` | `users.create` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin | OK |
| `POST` | `/users/{user}/activate` | `users.activate` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, ActivateUserRequest | OK |
| `POST` | `/users/{user}/deactivate` | `users.deactivate` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, DeactivateUserRequest | OK |
| `PUT` | `/users/{user}/role` | `users.role.update` | auth, EnsureUserIsActive, EnsureUserHasRole:beacon_admin, UpdateUserRoleRequest | OK |
