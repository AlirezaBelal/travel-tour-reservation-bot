# Maintained source snapshot — 2026-10-21

This repository distributes **source code only**, on the maintained
[master branch](https://github.com/AlirezaBelal/travel-tour-reservation-bot/tree/master).
It has not published a semantic-versioned GitHub Release and does not provide
a hosted Telegram bot, VPS, database, live webhook or production certification.

## Verified engineering baseline

- [Reviewed commit ef5a1c2](https://github.com/AlirezaBelal/travel-tour-reservation-bot/commit/ef5a1c2a12e0270fb98ecb926897e05fe09b43ff).
- [Nine successful CI jobs on that exact commit](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/runs/39572108378):
  PHP 8.0–8.4, MySQL 8.0, MariaDB 10.11, static/style gates, and synthetic
  Docker clean install, HTTP, worker recovery and backup/restore.
- These links are **fixed historical evidence**, not a CI result for later
  commits. Check the current HEAD and its own green CI before releasing.
- Consult the [architecture](ARCHITECTURE.md), [acceptance checklist](ACCEPTANCE.md),
  [engineering review](QUALITY_REVIEW.md) and [changelog](../CHANGELOG.md).

## Functional scope

Travelers browse tours, request group reservations, view booking status and
cancel when eligible. Operators manage tours, roles, payment review, reports
and notifications. Atomic booking/price/history/notification intent uses a
shared MySQL transaction and durable outbox. The code is a service-oriented
PHP modular monolith; historical commands retain a compatibility renderer.

The automated evidence includes PHPStan level 5 and scoped PSR-12 checks,
but neither full test coverage nor production security certification is claimed.

## Install, upgrade and recover

Run an installation with **your own authorized** Telegram token, HTTPS endpoint,
MySQL/MariaDB instance, encryption key and worker. See the
[English quick start](../README.md#quick-start) or
[Persian quick start](../README.fa.md#نصب-از-صفر).

For an existing deployment, plan downtime and a backup/restore rehearsal.
Apply additive migration lineage 001–003 using the
[migration](MIGRATION.md) and [deployment](DEPLOYMENT.md) guides. Migration
003 stores encrypted identifiers; it does not automatically convert old
plaintext values. Never assume a code-only rollback is safe after new bookings.

## Intentional limits

- One booking per user/tour, including after cancellation; companions counted
  without collecting their individual identities.
- CSV exports cap at 5,000 rows and exclude traveler identifiers.
- No live payment gateway or automatic refund; the signed payment simulator
  is strictly local and not a real provider integration.
- Telegram notification delivery is **at least once**; duplicate external
  messages may occur after acknowledgement failure.
- Dates are entered as Gregorian; monetary values use integer IRR.
- Real TLS/VPS hardening, webhook delivery, key recovery, real backups and
  payment settlement must be evaluated by the operator.
- Previously exposed bot credentials must be rotated/revoked by the owner.
  Deleting branches or editing source cannot revoke them.

See [security](../SECURITY.md) and
[repository administration](REPOSITORY_MAINTENANCE.md).
