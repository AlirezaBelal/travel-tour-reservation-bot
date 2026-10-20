# Contributing

Use a development branch and a reviewable PR. Keep GPL-3.0-only, migration lineage,
existing safe workflows and Persian user-facing messages. Never add real traveler
records, tokens, bank references or private logs to fixtures/issues/screenshots.

1. Install a maintained PHP version, Composer, cURL/PDO MySQL, libsodium for encrypted profiles, and a disposable MySQL 8 database.
2. Run `composer install` and `composer test:unit`.
3. Create a database named **travel_tour_test** with a dedicated test user.
4. Set TEST_MYSQL_DSN, TEST_MYSQL_USER and TEST_MYSQL_PASSWORD privately and run
   `composer test:integration`. These tests rebuild disposable fixtures.
5. Run `composer audit`, PHP syntax checks and `git diff --check`.
6. For entry-point and packaging changes, run the CI clean-container/HTTP/restore job.

The integration script order matters: legacy schema/migration → legacy workflows →
product migration and capacity → operations → new Telegram workflows. Use only
synthetic fixtures. Test the user-visible effect and unauthorized/replayed/failure
cases, not only a helper returning its own expected value.

Every capacity mutation must obey the tour locking protocol; every external
notification following a successful state change belongs in the same DB transaction.
Use integer IRR values and UTC timestamps. Never trust callback parameters for
ownership, permissions, amount or payment success. Use domain authorization even
when a menu button is hidden.

Migrations must be additive, versioned, resumable and rehearsed against the old
schema. Never drop old data as a shortcut. Document maintenance/backup/rollback
requirements. Destructive production operations require operator review.

Target `master` from a short-lived `fix/`, `docs/` or `feature/` branch. Keep a PR
draft while its implementation or required checks are incomplete. Remove merged
branches after their changes are integrated. `master` is the maintained source;
completed development reports remain in Git/PR history rather than installation guides.
Use [product scope](docs/PRODUCT.md) to distinguish a bug fix from a new feature.
Passing synthetic CI does not authorize live Telegram changes, merchant payments,
production deployment or a production certification.
