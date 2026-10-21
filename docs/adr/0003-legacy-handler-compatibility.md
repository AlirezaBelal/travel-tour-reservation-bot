# ADR 0003: Contain the include-based legacy handler

Status: Accepted — 2026-10-21

## Context

The historical `src/handler.php` uses lexical variables, message strings and
global output helpers. Rewriting all membership and broadcast commands together
would risk regressions and duplicate business rules.

## Decision

Route remaining legacy commands through `LegacyHandlerAdapter` inside the
existing `WebhookProcessor` transaction. Pass lexical dependencies explicitly.
`IncomingUpdate` maps supported private Telegram updates without I/O.
Test the actual adapter with synthetic MySQL/MariaDB workflows, and require the
architecture fitness test to find exactly one production handler include site.

## Consequences

The compatibility boundary is inspectable and regression-tested without
forcing new public APIs. The legacy handler is still effectful and has global
helper dependencies; migration may proceed command-by-command only when
behavioral tests cover it. The architecture test does not replace PHPStan or
comprehensive dependency analysis.
