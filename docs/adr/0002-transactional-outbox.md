# ADR 0002: Transactional outbox for Telegram notifications

Status: Accepted — existing implementation documented 2026-10-21

## Context

Telegram is an external API. A reservation must not commit without its durable
notification intent, nor should slow network calls hold MySQL row locks.

## Decision

Write booking changes, status history and `NotificationOutbox` events in the
owning PDO transaction. Dispatch outside the transaction with
`NotificationWorker`; recover leases, retry transient failures, and record
delivery state. Keep event-key idempotency and update receipts.

## Consequences

Database-side events are atomic with state changes; delivery is resilient to
worker restarts and Telegram throttling. Exactly-once delivery to Telegram
cannot be guaranteed across the external API/DB acknowledgement boundary; a
crash after a successful send can result in a duplicate user-visible message.
