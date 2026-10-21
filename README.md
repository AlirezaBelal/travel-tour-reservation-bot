# Travel Tour Reservation Bot

[![CI](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml)

Self-hosted, open-source **PHP/MySQL Telegram tour reservations** with a Persian traveler and admin interface. GPL-3.0-only. This repository provides source code, **not** a hosted bot or production certification.

## Features

- Browse, search and publish tours; reserve **1–20 seats** with transactional capacity and price snapshots.
- Track, approve, reject or cancel bookings. Operators review payments manually; there is **no live payment gateway or automatic refund**.
- Role-based administration, aggregate reports, privacy-safe CSV exports, reminders and a retryable notification outbox.
- Optional verified-own-contact and checksum-validated national-code onboarding with encrypted identifiers. These checks do not prove legal identity.
- Backward-compatible legacy commands, Docker Compose, additive migrations and automated tests.

## Quick start

Requires Docker Compose, your **own** Telegram bot token, an HTTPS domain and private credentials.

~~~bash
git clone https://github.com/AlirezaBelal/travel-tour-reservation-bot.git
cd travel-tour-reservation-bot
cp .env.example .env
# Edit .env privately. Create independent DB passwords and owner IDs.
# TELEGRAM_WEBHOOK_SECRET: openssl rand -hex 32
# PROFILE_ENCRYPTION_KEY: openssl rand -base64 32
docker compose build app
docker compose run --rm migrate php bin/install.php
docker compose run --rm migrate php bin/restrict-runtime.php --confirm
docker compose up -d
curl -f http://127.0.0.1:8080/health.php
~~~

Put an HTTPS reverse proxy in front of `127.0.0.1:8080`; only `public/` is web-accessible. After securing the domain, use the supplied webhook helper with your authorized bot:

~~~bash
docker compose run --rm app php bin/set-webhook.php https://YOUR_DOMAIN/index.php --check
# The following command contacts Telegram:
docker compose run --rm app php bin/set-webhook.php https://YOUR_DOMAIN/index.php --confirm
~~~

The `worker` **must be running** to send notifications and process scheduled tasks. Never commit `.env` or expose your bot token, encryption key or personal data. **Do not use `docker compose down -v` on valuable data.** For existing databases, follow [migration](docs/MIGRATION.md) and [backup/recovery](docs/DEPLOYMENT.md) before changing anything.

## Traveler and operator guide

Travelers: `/start` → profile → tour/search → select group size → confirm request → `/bookings` to track/cancel. Only the account holder's Telegram-shared contact is accepted; `/privacy` describes erasure. A booking request is **not** final confirmation.

Operators: configure `TELEGRAM_ADMIN_IDS`, open `/admin`, create/publish tours, review requests and manual payments, manage roles, reports and queued notifications. Never ask travelers to send bank credentials or national IDs in chat.

Prices are **integer IRR**, dates are Gregorian with a configurable display timezone, and storage uses UTC. One booking per user/tour remains even after cancellation. Pending requests hold seats; unpaid holds expire. `REQUIRE_VERIFIED_PROFILE` defaults to `false` for safe upgrades; enable only after establishing an identity-data collection policy. Old plaintext profile data is **not migrated automatically**.

Outbox messages are delivered **at least once**, so rare duplicate Telegram messages are possible. The signed payment sandbox is a **local simulator only**. Account erasure, retention, backup policy and deployment security remain operator responsibilities.

## Development

~~~bash
composer install
composer test:unit
# Integration tests require a disposable MySQL DB named travel_tour_test.
composer test:integration
composer install --working-dir=tools/quality
composer analyse
composer lint:style
~~~

CI covers PHP 8.0–8.4, MySQL, MariaDB and synthetic Docker recovery; it **does not** verify a live Telegram server or payment provider.

**Guides:** [Documentation](docs/README.md) · [Architecture](docs/ARCHITECTURE.md) · [Product limits](docs/PRODUCT.md) · [Deployment](docs/DEPLOYMENT.md) · [Security](SECURITY.md) · [Contributing](CONTRIBUTING.md) · [Changelog](CHANGELOG.md).

Licensed under [GPL-3.0-only](LICENSE).
