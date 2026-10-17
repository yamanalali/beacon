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
- `php artisan beacon:backup [--verify]` - encrypted archive of the database snapshot (SQLite `VACUUM INTO`, MySQL/MariaDB consistent SQL dump, or PostgreSQL custom-format dump), private documents and a manifest (row counts + document checksums).
- `php artisan beacon:backup:restore-test [id]` - decrypts the archive into a temporary folder and verifies every document checksum. SQLite runs `PRAGMA integrity_check` and compares table row counts. Native dumps are checksum-verified and restored into a newly created `beacon_restore_*` database; the table list is compared with the manifest and restored row counts are recorded. The disposable database is dropped in `finally`. The live database is never a restore target, and the default application connection is never replaced.
- `php artisan beacon:backup:prune` - refuses to run until a retention period is approved; always keeps the newest successful backup.

All runs are listed at `/operations/backups` (technical admin).

## Formal restoration exercise (required before final acceptance)
The automated restore test is a daily safety check only. Before final acceptance:
1. Provision a separate environment (not production).
2. Copy one backup file and the backup key there.
3. Decrypt and extract (`beacon:backup:restore-test` logic, or manual decryption with the key), restore `database.sqlite`, `database.sql` (MySQL/MariaDB), or `database.dump` (PostgreSQL) with the matching engine, and copy `documents/` into the new environment.
4. Log in as each role and verify records, documents and reports.
5. Record date, backup id, operator and result; Beacon signs off.

## Limitations / decisions
- Install the matching native clients on the application server: `mysqldump`/`mysql`, `mariadb-dump`/`mysql`, or `pg_dump`/`pg_restore`. PostgreSQL clients must be compatible with the server version.
- Configure executable paths with `BEACON_MYSQLDUMP_BINARY`, `BEACON_MARIADB_DUMP_BINARY`, `BEACON_MYSQL_BINARY`, `BEACON_PG_DUMP_BINARY` and `BEACON_PG_RESTORE_BINARY` when clients are outside PATH. `BEACON_BACKUP_COMMAND_TIMEOUT` defaults to 300 seconds.
- Native restore tests require CREATE/DROP DATABASE privileges. Set `BEACON_BACKUP_RESTORE_CONNECTION` to a separately configured Laravel connection on an isolated test server with the same database engine, or leave it empty to use the current server. Only generated scratch database names are used. Never configure production as a manual restore target.
- MySQL/MariaDB consistent snapshots require transactional tables (InnoDB). Do not run schema migrations during a backup. Native manifests verify the dump checksum and restored schema; unlike SQLite, they do not compare row counts against a separately queried live database, because that query would represent a different point in time.
- Database credentials are passed via a temporary mode-0600 MySQL client file or PostgreSQL environment variables, never command-line arguments or stored process output. The temporary credential file is deleted after each command.
- Archives are encrypted in memory; fine for pilot size, revisit for large document volumes.
- TODO: BUSINESS DECISION REQUIRED - final backup retention period and offsite location.
