# Database upgrades: migrations 001 and 002

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
There is no automatic destructive down migration. Post-upgrade write reconciliation has not been end-to-end verified.
CI also exercises a synthetic dump/restore through the container installation job.

## Automated validation

`tests/mysql_test.php` rebuilds only a database named `travel_tour_test` using
synthetic fixtures. Never point it at production. CI runs it on MySQL 8.0. It
checks real legacy-column migration, rollback on unknown status, repeat execution,
foreign keys, duplicate requests, duplicate approvals, eight concurrent PHP
request processes, automatic topic IDs and four-level deletion with cascading
membership removal. This verifies membership uniqueness, **not seat capacity**.
The workflow regression suite uses the production handler with a fake Telegram
boundary and real MySQL. The container job checks clean initialization, HTTP
readiness and synthetic backup/restore. The expanded suite runs on MySQL 8.0 and MariaDB 10.11. Live Telegram and clean VPS
installation remain separate external acceptance gates.

## Migration 002: independent tours and bookings

`php bin/migrate.php` now applies both versions. `php bin/install.php` initializes
a fresh baseline and applies both. Version 002 creates Tours, Bookings, participant
ordinals, status history, separate Payments, roles/audit, outbox, receipts, sessions
and rate limits. No legacy data/columns are deleted, and no capacity/date/price
is guessed from a TopicMembership. Create real tours through `/admin`.

Stop app and worker writers while migrating. Rehearse on a restored copy before
production. Version 002 uses resumable CREATE TABLE IF NOT EXISTS DDL and records
its marker after every table has been created. MySQL DDL commits implicitly.
Migration 001's data-copy transaction and its rollback tests remain unchanged.
A failed partial 002 can be retried; do not manually mark it applied or drop tables
containing new writes. Code-only rollback cannot represent new bookings/payments.

`tests/product_test.php` separately verifies actual seat-capacity races across
eight processes; this supplements the legacy membership uniqueness test.
