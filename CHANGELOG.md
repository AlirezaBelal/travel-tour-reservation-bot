# Changelog

## Architecture and CI hardening — 2026-10-21

- Isolated legacy handler execution behind a typed compatibility adapter; real
  MySQL/MariaDB legacy workflow tests now exercise the same adapter.
- Extracted pure Telegram update-context mapping from the HTTP bootstrap and
  added synthetic unit tests across PHP 8.0–8.4.
- Added a non-vacuous architecture fitness check that enforces the adapter as
  the sole production legacy include site (not a general PHP static analyzer).
- Documented C4-style context/components, the reservation/outbox sequence,
  and four architectural decisions with trade-offs.
- Updated GitHub-hosted checkout and artifact Actions to Node.js 24 releases.
  No runtime bot/server, payment provider, database schema, or public API changes.

## Source distribution — 2026-10-20

This dated source handoff is not a semantic-versioned release or production certification.

- Added independent tours with UTC dates, IRR price snapshots and publication controls.
- Added group bookings, atomic capacity, terminal-state history and ownership-safe cancellation.
- Added Persian guided operations, RBAC, audited manual payment review and restricted safe CSVs.
- Added durable outbox, retry/lease recovery, rate limits, reminders and expired unpaid holds.
- Added local signed payment simulation, account anonymization and retention controls.
- Added new domain/security/concurrency/workflow/HTTP/container/restore tests and bilingual setup guides.
- Added optional encrypted phone/national-code profiles with owner-only Telegram contact sharing, checksum validation, and migration 003; no category membership needed for booking.
- Preserved old membership migration and catalogue commands; redirected `/setprofile` to encrypted profiles and retired all unfinished plaintext profile states. Added regression coverage for unchanged historical values, own-account contact checks, authenticated encryption, fresh nonces and missing-key failure.
- Aligned the public product guides, source status and contribution workflow with the completed handoff on master.
- Removed temporary development-status reports from the distributed documentation; added a documentation index and a structured, secret-free bug report form.

The source is available on master. Operators supply their own hosting, credentials
and operational acceptance. No hosted service, real payment gateway or production
certification is included. See docs/PRODUCT.md and docs/ACCEPTANCE.md.

## Initial hardening development increment

Webhook authentication/private-chat isolation, relational topic memberships,
recursive category deletion, initial MySQL/workflow tests and Docker installation.
