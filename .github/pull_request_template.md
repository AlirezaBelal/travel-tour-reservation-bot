## Summary

Explain what problem is fixed and whether the public behavior or migration
contract changes.

## Verification

- [ ] Relevant unit, characterization and negative tests passed (or explain N/A).
- [ ] PHP 8.0–8.4 CI and static/style gates passed on the exact PR head.
- [ ] MySQL/MariaDB, concurrency and rollback reviewed for data changes (or N/A).
- [ ] Docker HTTP, recovery and backup/restore reviewed for runtime changes (or N/A).
- [ ] Authorization, receipt idempotency and transactional outbox preserved.
- [ ] No real token, encryption key, .env file, database dump or traveler data added.
- [ ] Docs, migration/rollback notes and compatibility impact recorded (or N/A).

Source-only: no live bot, webhook, server, database or payment gateway is deployed.
