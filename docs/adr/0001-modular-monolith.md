# ADR 0001: Keep a modular monolith

Status: Accepted — 2026-10-21

## Context

The source is a self-hosted PHP/MySQL Telegram reservation system. It has one
transactional data store, an HTTP webhook process and a separate notification
worker process. Capacity, booking state, payments and notification enqueueing
need consistent relational transactions.

## Decision

Keep a modular monolith with explicit presentation (webhook and Telegram UI),
application/business services (reservations, permissions, identity and payments),
and infrastructure (PDO, MySQL, outbox and transport) responsibilities.
Introduce testable seams **incrementally** instead of moving every file into
new directories. Keep one self-hosted deployment contract.

## Consequences

Simple install/restore, shared transactional boundaries and existing integration
tests are retained. Some application services still depend on PDO and legacy
helpers; that coupling is debt to reduce when a concrete testability problem
justifies it. No claim of complete Clean Architecture or microservice isolation.
