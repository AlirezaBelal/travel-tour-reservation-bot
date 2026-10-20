# Database upgrades: migrations 001, 002 and 003

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

`php bin/migrate.php` applies versions 001, 002 and 003. `php bin/install.php` initializes
a fresh baseline and applies all three. Version 002 creates Tours, Bookings, participant
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

## Migration 003: encrypted traveler identity

Migration 003 adds `TravelerIdentity` with two encrypted fields: verified own-account Telegram mobile and checksum-validated national code. It deliberately does not copy existing plaintext `Data.Mobile` or `Data.N_Code`; operators must assess legacy data and obtain an appropriate collection/legal basis separately. New registration never populates those legacy fields. Explicit account deletion cascades to encrypted identity data.

Set `PROFILE_ENCRYPTION_KEY` to a 32-byte random secret encoded as base64, generated using `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`. Put it only in secure runtime configuration, not in Git, tests using live data, issue descriptions, or log output. Keep a protected, separate backup of the key for recovery. Losing it makes encrypted identifiers permanently unreadable; changing it requires an intentional re-encryption procedure. Missing or invalid keys prevent new identity submissions (fail closed). Require the verified profile before reservation only after informing users and setting `REQUIRE_VERIFIED_PROFILE=true`; the default remains false for upgrades. Existing reservations and historical membership columns are unaffected.

Migrations require a maintenance database account with DDL privileges and should be tested first against a restore. The public bot should not be enabled until migration 003 and the secret are ready if verified profiles are required. Phone sharing via Telegram is evidence that the contact belongs to the sender's account, **not** proof of legal identity. National-code validation checks the control digit only; it is not a registry query.

### Retired plaintext forms

No schema change is needed for the security fix. `/setprofile` aliases `/profile`.
The next update from an unfinished legacy `name`, `contact`, `NationalCode`, `sex`
or `birthday` form resets only its routing state and discards that input. No
historical identity values are transformed or removed. Ask affected users to
restart via `/profile`. Stop all old application instances before upgrading;
otherwise an old process can still write plaintext. Keep backups encrypted and
access limited. Any conversion or deletion of historical personal data requires a
separate operator-approved retention/migration plan and restore rehearsal with
synthetic data. Never export those values into issues or support logs.
