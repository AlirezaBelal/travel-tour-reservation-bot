# ADR 0005: Explicit use cases with compatible APIs

Status: Accepted — 2026-10-21

## Context

The public source had a large Telegram controller, mixed tour/booking orchestration,
implicit legacy variables/output helpers and callers relying on old constructors,
include paths and profile-policy environment behavior. Moving files alone would
not improve testability; silently changing defaults could weaken or break installs.

## Decision

Keep one modular monolith and shared PDO transaction. Separate pure rules,
authorized read models, reservation/lifecycle/tour/payment/profile use cases and
focused conversation controllers. Compose a fixed graph explicitly in HTTP/CLI
factories; use no service locator or speculative repository interfaces.

Retain `BookingService` as a lazy compatibility facade, including named arguments.
The current runtime injects all use cases and a captured profile policy, so it
constructs no fallback operations. Only the marked `LegacyProfileRequirement`
adapter preserves dynamic environment reads for old constructor callers.

Keep historical payment/worker include paths as definition-only loaders. The
legacy renderer receives `LegacyUpdate`, authorized services and an explicit
responder; its single adapter include remains guarded until keyboard contracts
can be removed without behavior loss. Preserve global class names for compatibility.

## Consequences

Domain rules and application use cases can be tested without Telegram; direct
integration tests verify authority, policy, transactions and outbox. Source
architecture rules and injected violations enforce the new boundaries. Existing
real concurrency/HTTP/recovery/restore gates remain mandatory. A scoped dynamic
renderer and old API adapter are explicit debt rather than hidden application
state. PSR-4/namespaces and removal of compatibility paths are future migrations,
not prerequisites for this source refactor or reasons to rewrite the system.
