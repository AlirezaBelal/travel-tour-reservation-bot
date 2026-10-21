# ADR 0004: Separate encrypted traveler identity

Status: Accepted — existing design documented 2026-10-21

## Context

Some operators request an own-account Telegram-shared Iranian mobile number
and a checksum-valid national code. Those identifiers are sensitive; old
`Data.Mobile` and `Data.N_Code` fields exist for historical compatibility.

## Decision

Keep new identifiers in `TravelerIdentity` as libsodium secretbox ciphertext
with a fresh nonce and a 32-byte operator-held encryption key. Do not echo raw
identifiers in reports, messages or logs. Redirect old onboarding commands to
the secure profile and stop incomplete old plaintext flows. Keep migrations
additive and historical data untouched pending an operator-approved policy.

## Consequences

New identity values are encrypted at rest, and explicit account erasure removes
their record. Operators must keep the encryption key separately backed up and
define collection, retention and historical-data treatment. A national-code
checksum is **not** government identity verification. Key loss means ciphertext
cannot be recovered; existing historical plaintext requires separate handling.
