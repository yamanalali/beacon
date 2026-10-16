# Backup and restore runbook (section 56)

## Configuration (.env)
```
BEACON_BACKUP_ENABLED=true
BEACON_BACKUP_TIME=02:00
BEACON_BACKUP_DISK=local          # private disk; offsite copy needs an approved processor (cloud_backup)
BEACON_BACKUP_KEY=base64:...      # generate separately, store OUTSIDE the server/backup location
BEACON_BACKUP_RETENTION_DAYS=     # TODO: BUSINESS DECISION REQUIRED - leave empty until Beacon approves
```
The scheduler must run every minute: `php artisan schedule:run` (cron / Windows Task Scheduler).

## Commands
- `php artisan beacon:backup [--verify]` - encrypted archive of the database snapshot (`VACUUM INTO`), private documents and a manifest (row counts + document checksums).
- `php artisan beacon:backup:restore-test [id]` - decrypts the archive into a temporary folder, runs `PRAGMA integrity_check`, compares every table's row count and every document checksum with the manifest. Never touches the live database.
- `php artisan beacon:backup:prune` - refuses to run until a retention period is approved; always keeps the newest successful backup.

All runs are listed at `/operations/backups` (technical admin).

## Formal restoration exercise (required before final acceptance)
The automated restore test is a daily safety check only. Before final acceptance:
1. Provision a separate environment (not production).
2. Copy one backup file and the backup key there.
3. Decrypt and extract (`beacon:backup:restore-test` logic, or manual decryption with the key), place `database.sqlite` and `documents/` in the new environment.
4. Log in as each role and verify records, documents and reports.
5. Record date, backup id, operator and result; Beacon signs off.

## Limitations / decisions
- Only SQLite is implemented. TODO: BUSINESS DECISION REQUIRED - production database engine/hosting (add the native dump).
- Archives are encrypted in memory; fine for pilot size, revisit for large document volumes.
- TODO: BUSINESS DECISION REQUIRED - final backup retention period and offsite location.
