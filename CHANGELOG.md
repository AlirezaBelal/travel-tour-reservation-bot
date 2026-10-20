# Changelog

## Source distribution — 2026-10-20

This dated source handoff is not a semantic-versioned release or production certification.

- Added independent tours with UTC dates, IRR price snapshots and publication controls.
- Added group bookings, atomic capacity, terminal-state history and ownership-safe cancellation.
- Added Persian guided operations, RBAC, audited manual payment review and restricted safe CSVs.
- Added durable outbox, retry/lease recovery, rate limits, reminders and expired unpaid holds.
- Added local signed payment simulation, account anonymization and retention controls.
- Added new domain/security/concurrency/workflow/HTTP/container/restore tests and bilingual setup guides.
- Added optional encrypted phone/national-code profiles with owner-only Telegram contact sharing, checksum validation, and migration 003; no category membership needed for booking.
- Preserved old membership migration and established legacy commands.
- Aligned the public product guides, source status and contribution workflow with the completed handoff on master.

The source is available on master. Operators supply their own hosting, credentials
and operational acceptance. No hosted service, real payment gateway or production
certification is included. See docs/PRODUCT.md and docs/ACCEPTANCE.md.

## Initial hardening development increment

Webhook authentication/private-chat isolation, relational topic memberships,
recursive category deletion, initial MySQL/workflow tests and Docker installation.
