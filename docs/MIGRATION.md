# Migration 001: relational topic memberships

This branch still implements participation requests, not seat inventory or payments.
Migration 001 replaces `Data.topic<id>` reads and writes with `TopicMemberships`.
It preserves pending (`check`) and approved (`verify`) values. Null/empty values
remain absent. Unknown statuses and populated columns referring to missing topics
stop migration instead of silently losing data. Unique and foreign keys enforce
one membership per traveler/topic and prevent orphan records.

## Upgrade an existing installation

1. Stop webhook processing and all writers. Return HTTP 503 from the reverse proxy
   during the maintenance window so Telegram can retry delivery. Do not run old
   and new versions concurrently.
2. Back up the complete database using `mysqldump --single-transaction` (InnoDB)
   with credentials supplied through a protected client option file. Store the
   backup outside the web root and restrict access; it contains personal data.
3. Restore that backup into an isolated database first and run the migration there.
4. Install this branch's dependencies with `composer install --no-dev` and configure
   `.env`. Run `php bin/migrate.php` using a separate database account allowed to
   create tables. The application account no longer needs ALTER TABLE permission.
5. Verify legacy pending/approved counts against `TopicMemberships`; verify a
   synthetic profile, catalogue and approval flow before enabling traffic.
6. Enable the new application. Register the mandatory webhook secret with Telegram.

For a fresh installation, import `topic.sql` and then run `php bin/migrate.php`.
The CLI is idempotent: the version marker is committed in the same transaction
as the copied data. A MySQL advisory lock excludes another copy of this migration.
DDL is additive and commits separately, following MySQL's DDL behavior. A failed
copy can leave empty new tables; retry after resolving the reported data issue.
Legacy columns are deliberately retained and never dropped by the application.

## Rollback

Before reopening traffic, the old version can use the unchanged legacy data.
After new writes, legacy columns are stale: **do not switch back to the old code**
without reconciling those writes. Stop traffic, preserve a new backup, and restore
both the prior application and the matching pre-upgrade database only after
reviewing the data-loss implications and obtaining operator authorization.
There is no automatic destructive down migration. Restore and post-upgrade write
reconciliation have not yet been end-to-end verified.

## Automated validation

`tests/mysql_test.php` rebuilds only a database named `travel_tour_test` using
synthetic fixtures. Never point it at production. CI runs it on MySQL 8.0. It
checks real legacy-column migration, rollback on unknown status, repeat execution,
foreign keys, duplicate requests, duplicate approvals, eight concurrent PHP
request processes, automatic topic IDs and four-level deletion with cascading
membership removal. This verifies membership uniqueness, **not seat capacity**.
MariaDB, full Telegram workflows, backup/restore and clean VPS installation remain
separate acceptance gates.
