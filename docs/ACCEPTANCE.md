# Acceptance gates and continuation record

This file defines executable gates; a test being listed is not proof that it passed.
The PR records exact commit/run links. Keep PR #4 draft until all required gates
and operator reviews are satisfied. No final release or deployment is authorized.

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
| Reminder replay/cancellation, hold expiry, erasure/retention | `tests/operations_test.php` |
| Persian forms, booking/review/cancel/manual pay, RBAC, CSV and rate limits | `tests/product_workflow_test.php` |
| Real webhook entry point and payment endpoint | `tests/http_e2e.php` copied only into disposable CI container |
| Fresh install, migrations, readiness, auth and source isolation | CI `install` job |
| Runtime has no root secret and no DDL privilege, with working HTTP bookings | `tests/runtime_permissions.php` + HTTP suite after privilege restriction |
| Dump/restore and preserved synthetic fixture | CI `install` job, disposable volume, then seven booking/payment/history/queue relationship assertions |
| Syntax, Composer metadata/platform compatibility, dependency audit | CI PHP matrix |
| Live Telegram token/webhook, real delivery/429 behavior | **BLOCKED EXTERNAL** — token and operator-run test bot |
| Linux VPS/TLS/domain/reverse proxy/firewall/restart rehearsal | **BLOCKED EXTERNAL** — infrastructure and operator authorization |
| Real merchant gateway or provider-hosted sandbox | **BLOCKED EXTERNAL** — merchant account, credentials and official API review |
| Real refunds/financial settlement | **BLOCKED EXTERNAL** — policy, integration and authorization |
| Production backup encryption/offsite storage/recovery objectives | **BLOCKED EXTERNAL** — operator storage and restore rehearsal |
| Rotate any real token exposed in old Git history | **BLOCKED EXTERNAL** — BotFather account; repository cleanup does not revoke tokens |
| GitHub About description/topics | Maintainer setting; connector currently exposes no repository metadata update operation |

## Explicit limitations

No automatic refund, live gateway, ticketing or external booking provider exists.
The local payment simulator is not a payment gateway certification. Sending is
at least once because external acknowledgement cannot be committed atomically
with MySQL. One booking per user/tour is retained after terminal transitions.
Group companions are counted, not required to disclose identities. CSVs cap at
5,000 rows and exclude traveler identity columns. Dates are Gregorian, currency IRR.

## Safe continuation

Start by reading the live PR head and compare it with your local branch. Preserve
other commits. Publish with an expected-head lease; do not force-push over changes.
Use the same branch `codex/production-hardening`, preserve migration 001, and add
new numbered migrations for future schema changes. Existing tests must run before
new integration suites. No test may use a real traveler record or live payment.

The authoring environment does not contain PHP/Composer/MySQL/Docker; runtime
verification is performed in GitHub Actions. Do not report skipped/local-unavailable
tests as passed. CI failures are corrected before recording a successful gate.
