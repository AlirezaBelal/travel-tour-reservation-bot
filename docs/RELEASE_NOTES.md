# Unreleased development notes

This is a draft development build, not a published release or release candidate.
The essential flow is tour selection → group request → pending seat hold → payment
review when needed → booking approval → user cancellation or manager completion,
with transactional history and outbox notifications.

Upgrade requires a maintenance window, backup/restore rehearsal and both migration
001 and 002 before new traffic. Legacy data is retained; legacy memberships remain
independent of real tours. New product tables are not backward-writable by old code.
Never perform a code-only rollback after new booking data has been written.

Operational dependencies: an HTTPS webhook, your own bot token, a running queue
worker and reliable MySQL storage. Payment/refund gateways, live Telegram delivery,
VPS/TLS validation and production recovery objectives are BLOCKED EXTERNAL.
The shipped sandbox is a local simulator, not a gateway sandbox certification.

Known explicit limits: one booking per user/tour even after cancellation; group
members are counted without collecting individual identities; CSV export is capped
at 5,000 rows; no automatic refunds; outbox delivery is at least once; Gregorian
date entry and integer IRR currency. Do not describe these as implemented beyond
these contracts. Acceptance evidence is recorded in the PR and docs/ACCEPTANCE.md.
