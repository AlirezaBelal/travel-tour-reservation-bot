# Testing and acceptance

This guide maps product behavior to repeatable tests. A listed test is not proof
of a successful run: inspect the Actions result for the exact commit you install.
[CI](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions) runs PHP
8.0–8.4, MySQL 8.0, MariaDB 10.11 and disposable Docker installation/recovery.
All fixtures are synthetic; never point a test suite at a traveler database.

## Run the checks

Follow the environment setup in [CONTRIBUTING.md](../CONTRIBUTING.md), then run:

```bash
composer install
composer test:unit
# Privately configure TEST_MYSQL_DSN, TEST_MYSQL_USER and TEST_MYSQL_PASSWORD.
# The database must be disposable and named exactly travel_tour_test.
composer test:integration
composer audit
git diff --check
```

`test:integration` rebuilds its fixtures. Preserve script order: legacy migration,
legacy workflows, product/concurrency, operations, Telegram workflows, worker
process recovery. The Docker/HTTP/separate-volume restore scenarios run in CI.

## Automated evidence and operator checks

| Gate | Reproducible evidence |
|---|---|
| Unit validation/state/CSV rules | `composer test:unit` on PHP 8.0–8.4 |
| Old database migration, repeat/rollback/FKs | `tests/mysql_test.php` with synthetic legacy columns |
| Legacy behavior preservation | `tests/workflow_test.php` with production legacy handler + MySQL |
| Group seats, price/payment snapshots, duplicate requests/history | `tests/product_test.php` |
| No overbooking across 8 processes, including stale transaction snapshots | `tests/product_test.php worker` launched by parent test |
| Queue rollback atomicity | Failing synthetic outbox trigger in product integration test |
| Sandbox signature, amount, order, expiry, success/failure/replay | `tests/operations_test.php` + actual HTTP callback in container |
| Queue 429/5xx/permanent errors/backoff/leases/manual retry | `tests/operations_test.php`, fake external transport |
| Real worker process kill, advisory-lock exclusion, persisted retry and lease recovery | `tests/worker_process_test.php` on both database engines; synthetic transport, actual subprocesses |
| Database outage, supervisor restart, fresh worker connection and web restart | `tests/container_restart.sh` in disposable Compose project before any queued notifications |
| Reminder replay/cancellation, hold expiry, erasure/retention | `tests/operations_test.php` |
| Persian forms, booking/review/cancel/manual pay, RBAC, CSV and rate limits | `tests/product_workflow_test.php` |
| Telegram own-contact validation, national checksum, encrypted profile storage and booking gating | `tests/validators_test.php`, `tests/product_workflow_test.php`; legacy plaintext remains untouched |
| Retired `/setprofile` plaintext path and all five unfinished legacy states; old values preserved | `tests/workflow_test.php`, `tests/product_workflow_test.php`, `tests/http_e2e.php` |
| Authenticated identifier encryption, fresh nonces, missing-key failure and encrypted identity erasure | `tests/product_workflow_test.php` |
| Real webhook entry point and payment endpoint | `tests/http_e2e.php` copied only into disposable CI container |
| Fresh install, migrations, readiness, auth and source isolation | CI `install` job |
| Runtime has no root secret and no DDL privilege, with working HTTP bookings | `tests/runtime_permissions.php` + HTTP suite after privilege restriction |
| Dump/restore and preserved synthetic fixture | CI `install` job, second Compose project and empty new volume, then seven booking/payment/history/queue relationship assertions |
| Syntax, Composer metadata/platform compatibility, dependency audit | CI PHP matrix |
| Live Telegram token/webhook, real delivery/429 behavior | **BLOCKED EXTERNAL** — token and operator-run test bot |
| Linux VPS/TLS/domain/reverse proxy/firewall/restart rehearsal | **BLOCKED EXTERNAL** — infrastructure and operator authorization |
| Real merchant gateway or provider-hosted sandbox | **BLOCKED EXTERNAL** — merchant account, credentials and official API review |
| Real refunds/financial settlement | **BLOCKED EXTERNAL** — policy, integration and authorization |
| Production backup encryption/offsite storage/recovery objectives | **BLOCKED EXTERNAL** — operator storage and restore rehearsal |
| Rotate any real token exposed in old Git history | **BLOCKED EXTERNAL** — BotFather account; repository cleanup does not revoke tokens |

## Explicit limitations

No automatic refund, live gateway, ticketing or external booking provider exists.
The local payment simulator is not a payment gateway certification. Sending is
at least once because external acknowledgement cannot be committed atomically
with MySQL. One booking per user/tour is retained after terminal transitions.
Group companions are counted, not required to disclose identities. CSVs cap at
5,000 rows and exclude traveler identity columns. Dates are Gregorian, currency IRR.

## Before admitting real travelers

On your authorized installation, use test accounts and a synthetic free tour to
verify onboarding, discovery, party reservation, administrator approval/rejection,
capacity limits, notification delivery and cancellation. Verify role revocation,
private reports and account deletion. Rehearse a worker/database restart and a
backup restore into a different database volume. Establish your retention,
key-recovery and manual-payment/refund procedures before collecting personal data.

Never replace another bot's webhook, migrate real records or run payment tests
without the operator's authorization. A green CI result validates the synthetic
source tests; it does not validate your Telegram account, TLS, server or backups.
