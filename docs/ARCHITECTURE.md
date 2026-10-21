# Architecture

**Service-oriented modular monolith:** one PHP application, one MySQL/MariaDB database, separate webhook and worker processes. No framework container, microservices or hosted services.

~~~text
Telegram webhook → HttpIngress / IncomingUpdate → WebhookProcessor
                                                → ProductBot (router)
                                                → focused controllers
                                                → authorized services + pure rules
                                                → PDO / transactional outbox
MySQL/MariaDB → NotificationWorker → Telegram transport
~~~

## Responsibilities

| Layer | Ownership |
|---|---|
| HTTP | `public/index.php`, `HttpIngress`: secret, request and update validation; no database before authentication |
| Composition | `BookingServiceFactory`, `ProductBotFactory`: explicit dependency creation |
| UI | `ProductBot` routes to `TourController`, `BookingController` and `TravelerProfileController`; `BotConversation` owns per-update form context |
| Application | `TourService`, `ReservationService`, `BookingLifecycleService`, `PaymentReviewService`, profile, query and report services: permissions and use cases |
| Rules | `TourRules`, `BookingRules`, `PaymentRules`: no PDO or Telegram coupling |
| Persistence | `TourInventory`, `BookingReader`, `BookingHistoryStore` and PDO-backed services |
| Delivery | `Outbox`, `NotificationWorker`, `Scheduler`, `TelegramTransport` |
| Compatibility | `BookingService` preserves the public API; `LegacyHandlerAdapter` is the only include boundary for `handler.php` |

## Transaction and concurrency contract

- `Database::atomic` joins an existing transaction without committing it; an owning transaction retries MySQL lock errors 1205/1213 at most five attempts. `TransactionCleanup` checks whether PDO still has an active transaction before rollback.
- **Reserve:** lock user → tour → existing booking/capacity. Verify published tour, start time, profile policy, expected version and free seats. Booking, participants, price/payment snapshot, history and queued messages commit together.
- **Change booking status:** owner/reviewer authorization, tour lock → booking lock → payment lock; verify fresh state and payment before writing status, history, audit and outbox.
- **Manual payment:** tour → booking → payment lock; unique hashed reference, audit and outbox within the same transaction.
- **Edit tour:** enforce version and held-capacity invariants under locks. Outbox intents join the owning transaction.
- **Worker:** short database claims under a single-worker advisory lock; external Telegram calls happen outside the claiming transaction. Delivery is **at least once**, never guaranteed exactly once.

See [migrations and rollback](MIGRATION.md) before altering a deployed schema.

## Security and boundaries

The authenticated private-chat sender determines the actor; callback data never grants authority. Domain services enforce ownership/RBAC independently of UI visibility. Contacts must originate from the account's own Telegram share; mobile/national code are encrypted with libsodium and an operator-held key. Reports exclude personal identity columns. Account erasure retains only allowed anonymized records; offline backups and previous Telegram messages require separate operator policy.

Legacy memberships, commands and the old include-based renderer are deliberately retained. The scoped renderer performs no direct SQL and shares pure keyboard builders. PHPStan/PHPCS include it; characterization tests preserve its command/callback payloads. New services must not take dependencies on the legacy renderer.

## Quality gates and debt

CI runs PHP 8.0–8.4, MySQL, MariaDB, rollback/concurrency tests, Docker install/recovery/restore and PHPStan level 6 plus PHPCS across all `src/`, including the legacy template. Architecture tests inject synthetic violations. These checks reduce risk, not prove production readiness. Remaining debt: legacy rendering, partially dynamic conversation/Telegram payloads, static-analysis depth beyond level 6 and incomplete numeric coverage measurement.

Further rationale: [ADR 1](adr/0001-modular-monolith.md) · [ADR 2](adr/0002-transactional-outbox.md) · [ADR 3](adr/0003-legacy-handler-compatibility.md) · [ADR 4](adr/0004-encrypted-traveler-identity.md) · [ADR 5](adr/0005-explicit-use-cases.md). [Security](../SECURITY.md) · [Acceptance](ACCEPTANCE.md).
