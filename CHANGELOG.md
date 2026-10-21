# Changelog

## Explicit conversation and ingress dependencies — 2026-10-21

- Extracted profile UI into a focused controller and shared per-update conversation
  context; preserved response sequencing, contact consent and erasure flows.
- Replaced legacy global output helpers with an injected responder/transport and
  separated pure HTTP ingress decisions from database composition.
- Added HTTP, responder and actor/form/response-key isolation coverage.

## Legacy application boundaries — 2026-10-21

- Extracted legacy membership eligibility/approval and broadcast recipient/form
  operations; moved category deletion confirmation into the existing topic service.
- Replaced mixed adapter inputs with LegacyUpdate; the compatibility renderer no
  longer receives Medoo or performs persistence. Preserved legacy broadcast behavior
  with characterization, direct authorization and architecture-mutation coverage.

## Tour management separation — 2026-10-21

- Separated pure tour validation and tour editing/publication from booking aggregate
  operations. Shared tour/capacity row locks and compatibility APIs are preserved.
- Added direct tour rollback/capacity tests and a real confirmation/cancellation race.

## SQL-free product presentation — 2026-10-21

- Extracted profile and booking-list/history operations with direct service
  authorization; ProductBot no longer queries PDO or constructs its services.
- Added an explicit product composition factory, direct integration coverage and
  mutation-tested architecture guards over real source.

## Independent manual payment review — 2026-10-21

- Extracted payment review from BookingService, keeping its public compatibility
  delegate and the existing tour/booking/payment lock order. HTTP/CLI wiring is
  explicit; Telegram now calls the payment application service directly.
- Shared pure payment reference rules between the form and service. Added unit,
  rollback, outbox-failure and four-process manual-review concurrency tests.

## Legacy category application flow — 2026-10-21

- Extracted configured-owner category creation and caption forms from the legacy
  handler into an explicitly wired service without global or Telegram dependencies.
- Preserved legacy commands and response text; added direct permission, form-state
  and transaction rollback coverage on MySQL/MariaDB.

## Catalogue application service — 2026-10-21

- Characterized catalogue visibility, literal search, ordering and pagination before
  extraction, with synthetic integration fixtures on MySQL and MariaDB.
- Moved catalogue SQL and visibility authorization out of Telegram presentation
  into an explicitly injected `TourCatalogService`, preserving transaction and
  capacity-lock behavior. Added direct service tests and architecture guards.

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
