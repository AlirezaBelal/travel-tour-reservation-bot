# Travel Tour Reservation Bot

[![CI](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml) · [فارسی](README.fa.md)

A self-hosted Persian Telegram bot for tour discovery, group reservations, manual
payment review and tour operations. PHP, MySQL, GPL-3.0-only.

**Development preview — the PR remains draft.** Automated tests use synthetic
travelers, a simulated Telegram transport and a local signed payment simulator.
A live Telegram installation, VPS/TLS deployment and merchant payment gateway are
separate external acceptance gates. Public source code does not provide a hosted bot.

## Features

- Minimal profile: group leader name; no mobile or national ID required for new bookings.
- Hierarchical categories independent of dated tours; destination/title search and pagination.
- Tour dates, IRR integer pricing, publication controls, capacity and group sizes 1–20.
- Transactional seat inventory: pending and confirmed reservations consume capacity.
- Pending → confirmed/rejected/cancelled and confirmed → cancelled/completed, with history.
- Ownership checks, duplicate webhook/booking protection and stale tour-price confirmation checks.
- Persian guided Telegram administration, manager/reviewer/reporter roles and owner controls.
- Free bookings, separate manual payment review, unique transaction references and a local sandbox adapter.
- Durable database outbox, exponential retry, Telegram retry_after handling, rate control and failed-send review.
- Upcoming confirmed-tour reminders, expired unpaid hold rejection and account anonymization.
- Restricted CSV reports without traveler profile columns; formula neutralization and export audit.
- Docker Compose, readiness checks, additive legacy migrations and synthetic backup/restore CI.

Existing `/list`, `/setprofile`, topic administration, membership approval and broadcast
commands remain available for compatibility. Legacy memberships are not invented
into dated tours or bookings: an operator creates real tours separately.

## Quick start

Requirements: Git, Docker Engine with Compose, and an independently created Telegram
bot token. The maintained container uses PHP 8.4 and MySQL 8.0.

```bash
git clone --branch codex/production-hardening https://github.com/AlirezaBelal/travel-tour-reservation-bot.git
cd travel-tour-reservation-bot
cp .env.example .env
# Edit .env: token, owner Telegram IDs, webhook secret and distinct database passwords.
# Generate a webhook secret: openssl rand -hex 32
docker compose build app
docker compose run --rm migrate php bin/install.php
docker compose run --rm migrate php bin/restrict-runtime.php --confirm
docker compose up -d
curl -f http://127.0.0.1:8080/health.php
```

The `worker` service is essential: it sends queued messages, schedules reminders and
runs configured retention. Without it, messages remain queued. Do not run the local simulator
against real customer orders. Keep `PAYMENT_SANDBOX_ENABLED=false` in normal use.

Place an HTTPS reverse proxy on your domain in front of `127.0.0.1:8080`. In
Telegram, open **@BotFather**, run `/newbot`, choose a name and a unique bot username,
and store the resulting token privately in `.env`. Register
`https://YOUR_DOMAIN/index.php` using Telegram's `setWebhook`, setting `secret_token`
to the same `TELEGRAM_WEBHOOK_SECRET` and `allowed_updates` to
`["message","callback_query"]`. Use a protected local configuration/script for
credentials; never paste them into issues, screenshots or shared command logs.
After configuring your domain/TLS, use the supplied credential-safe helper:

```bash
docker compose run --rm app php bin/set-webhook.php https://YOUR_DOMAIN/index.php --check
# This next command contacts Telegram; run only with your own authorized bot/domain:
docker compose run --rm app php bin/set-webhook.php https://YOUR_DOMAIN/index.php --confirm
```

`--check` validates configuration syntax only. `--confirm` performs real registration
without printing credentials and preserves pending updates. Then open your bot in
a **private chat** and send `/start`.

Only `public/` is a web document root. Never expose `.env`, repository root,
dependencies, migrations or backups. The database is not published on a host port.
For an existing installation, use the [upgrade guide](docs/MIGRATION.md) instead
of treating the database as disposable. **Never use `docker compose down -v` on
an installation whose data you need.**

For optional synthetic draft data, set `APP_ENV=development` in a disposable
configuration, then run `docker compose run --rm app php bin/seed-demo.php --confirm`.
It is repeatable and creates no real traveler or payment. Restore production mode
before normal use. The demo remains draft until explicitly published.

## Traveler and operator guide

Travelers: `/start` → **پروفایل** → enter leader name → **تور‌ها** or **جست‌وجوی تور** →
view destination/dates/price/remaining capacity → enter number of people → confirm.
Use **رزروهای من** for status/history or cancellation. `/cancel` exits a form;
`/help` explains the flow and `/privacy` explains deletion and retention.

Owners: put your numeric Telegram user ID in `TELEGRAM_ADMIN_IDS`, then `/admin`.
Create categories and draft tours using the Persian wizard; publish when complete.
Review pending requests; paid tours require manual payment verification before
booking confirmation. Enter only an operator receipt reference, never bank/card
or national-ID details. Review reports, queue failures and broadcasts in the same
menu. Role changes, account deletion, category deletion and tour archiving require
confirmation. Managers can view only the leader name, with an audit event; CSVs
exclude name, phone, national code and Telegram user ID.

| Role | Permissions |
|---|---|
| Owner (environment) | All operations and role assignment |
| Manager | Tours/categories, bookings, payment review, reports/export, essential profile name, notifications |
| Reviewer | Booking review and aggregate reports; no payment/export/profile access |
| Reporter | Aggregate reports only |
| Traveler | Own profile, bookings and cancellation |

Dates entered in the wizard are Gregorian, e.g. `2027-04-21 08:30`, in
`APP_TIMEZONE` (default Asia/Tehran). Storage and scheduling use UTC. Amounts are
integer **Iranian rials (IRR)**, not tomans. Pending reservations hold seats. Unpaid
pending holds expire after 24 hours and are rejected by the scheduler. A traveler
may cancel before the tour starts. A manager completes a confirmed booking after
its tour ends. One booking record per user/tour is retained even after cancellation;
self-service rebooking of that same tour is not currently supported.

## Payments and delivery limits

Payment status is separate from reservation status. There is no live gateway,
automatic debit or automatic refund. Manual payment review records a hashed unique
reference. Cancellation of a paid booking frees seats but does not return funds;
the operator must arrange and record settlement outside this bot.

`SandboxPaymentAdapter` is a **local signed simulator**, not an integrated provider
sandbox. With an explicit test key and enable flag, `/payments/sandbox.php` verifies
an HMAC event, freshness, exact order, amount, expiration and transaction uniqueness.
`bin/sandbox-payment.php` generates such local test events. A real gateway adapter
must verify the transaction against its provider's authoritative server API using
reviewed official documentation and a merchant account. That integration is
**BLOCKED EXTERNAL**; never consider callback query parameters proof of payment.

Outbox insertion is atomic with booking changes. Event keys prevent duplicate
queue entries and update receipts prevent repeated webhook mutations. Telegram
sending is **at least once**: a process/network failure after Telegram accepts a
message but before the database records success can cause a repeated message on
retry. Telegram does not supply a sendMessage idempotency key; this project does
not claim exactly-once delivery. Unknown/permanent failures are visible for review;
8 exhausted attempts require authorized manual retry. Worker leases recover after
five minutes. Delivery is conservatively limited globally and per chat; 429 pauses
pending delivery according to retry_after. [Official API](https://core.telegram.org/bots/api#responseparameters)
· [Telegram rate guidance](https://core.telegram.org/bots/faq#my-bot-is-hitting-limits-how-do-i-avoid-this).

Automatic profile anonymization is **disabled by default** to protect existing
installations during upgrade. After approving and publishing a retention policy,
set `RETENTION_ENABLED=true` and `RETENTION_DAYS` (default 180). Explicit user-requested
erasure remains available. Sent/failed queue payloads expire after seven days;
update receipts retain a 30-day replay window. A minimal ingress counter remains
for up to one day after account deletion to prevent resetting spam limits. Already-delivered/in-flight Telegram
messages and offline backups cannot be recalled by a database deletion.

## Development and verification

```bash
composer install
composer test:unit
# Create a disposable MySQL database named exactly travel_tour_test.
export TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=travel_tour_test;charset=utf8mb4'
export TEST_MYSQL_USER=your_test_user
# Set TEST_MYSQL_PASSWORD privately.
composer test:integration
composer audit
```

Integration tests rebuild **only disposable fixtures** and must never target a real
database. CI checks PHP 8.0–8.4 compatibility; use a maintained PHP version for
hosting. It exercises old-schema migration, old Telegram workflows, tour booking
and group capacity across concurrent PHP processes (including pre-existing read
snapshots), role/ownership checks, payment simulation, retries/reminders/deletion,
guided new Telegram workflows, actual HTTP entry points, container installation
and dump/restore. See [acceptance gates](docs/ACCEPTANCE.md),
[contribution guide](CONTRIBUTING.md), [architecture](docs/ARCHITECTURE.md),
[operations](docs/DEPLOYMENT.md), [security](SECURITY.md) and [changelog](CHANGELOG.md).

No test run proves your external Telegram token, HTTPS certificate, VPS security,
backup storage or merchant account works. External gates remain explicitly open.

## Discoverability and license

Suggested GitHub Topics: `telegram-bot`, `tour-booking`, `travel-reservation`, `php`,
`mysql`, `booking-system`, `open-source`, `telegram-booking-bot`.
Suggested description: “Self-hosted Persian Telegram tour reservations with group
capacity, admin review, manual payments and durable notifications.”
These values must be applied in repository **About** settings by an authorized
maintainer; a README does not change GitHub metadata.

Licensed under **GPL-3.0-only**. See [LICENSE](LICENSE).
