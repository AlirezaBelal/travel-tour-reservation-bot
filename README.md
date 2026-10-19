# Travel Tour Reservation Bot

[![CI](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml)

A PHP/MySQL Telegram workflow for traveler onboarding, hierarchical tour-group discovery, participation requests, administrator approval, and targeted member communication.

[وضعیت توسعه به فارسی](docs/STATUS.fa.md) · [Migration and upgrade guide](docs/MIGRATION.md)

**Development branch:** not production ready. Run migration 001 before using this version.

## What this repository implements

The bot coordinates the operational flow around joining travel groups and tours. A user completes a profile, browses a hierarchical catalogue, requests participation, and waits for an administrator decision. Administrators can maintain the topic tree, approve requests, and send messages to selected groups or all completed profiles.

This repository should be interpreted as a **reservation/request workflow**, not as a complete travel-commerce platform. It does not implement online payment, automated seat inventory, ticket issuance, or external booking-provider integration.

### Implemented capabilities

- Telegram webhook entry point
- Multi-step traveler profile onboarding
- Iranian mobile ownership check through Telegram contact sharing
- Iranian national-code validation
- Birth-date and gender capture
- Hierarchical tour/group catalogue
- Participation request state: pending / approved
- Administrator approval callbacks
- Administrator topic creation and deletion
- Broadcast messaging to all completed profiles
- Targeted messaging to approved members of a selected topic
- MySQL persistence through Medoo
- Environment-based credential handling
- Required Telegram webhook-secret verification
- Administrator-only protection for sensitive callbacks
- PHP CI, syntax checks, validation tests, secret-pattern checks, and dependency audit

## Architecture

```text
Telegram user
      ↓
Telegram Bot API / webhook
      ↓
index.php
      ↓
config.php
      ├── environment / secrets
      ├── webhook verification
      ├── Telegram API client
      └── database bootstrap
      ↓
Conversation state in Data
      ↓
Hierarchical Topics catalogue
      ↓
Participation request
      ↓
Administrator review
      ↓
Pending / approved membership state
      ↓
Targeted operational messaging
```

## User flow

1. The user starts the bot with `/start`.
2. `/setprofile` begins the profile flow.
3. The user provides name, Telegram-shared mobile number, national code, gender, and birth date.
4. `/list` displays available root tour/group topics.
5. The user navigates nested topics and submits a participation request.
6. The request is marked pending and sent to the configured administrators.
7. An administrator approves the request through an inline callback.
8. The user receives an approval message and the topic is marked approved for that user.

The user-facing conversation is Persian because the original product targeted Persian-speaking travelers.

## Administrator flow

Configured administrators can:

- maintain root and nested topics
- review participation requests
- approve requests
- send a Telegram message to all completed profiles
- target a message to approved members of a selected topic
- inspect basic runtime diagnostics

Sensitive administrative callback actions are rejected for non-admin Telegram users.

## Requirements

- PHP 8.0+
- Composer
- PHP cURL, JSON, and PDO extensions
- MySQL or MariaDB
- Telegram Bot API token
- HTTPS webhook endpoint

Dependencies are managed only through the root Composer project. Vendored dependency copies are intentionally not tracked.

## Local setup

Install dependencies:

```bash
composer install
```

Create local configuration:

```bash
cp .env.example .env
```

Configure `.env` with the Telegram token, administrator Telegram IDs, and database credentials. Never commit the populated `.env` file.

Initialize a fresh database:

```bash
mysql -u <user> -p < topic.sql
php bin/migrate.php
```

Expose `index.php` through HTTPS and register that endpoint as the Telegram webhook.

Set `TELEGRAM_WEBHOOK_SECRET` to a random 32–256 character value using only letters, digits, underscores or hyphens (for example, `openssl rand -hex 32`). Register the same secret with Telegram. Missing or invalid secrets are rejected. The bot only accepts POST requests and private conversations where the chat belongs to the sender. Edited messages, group messages and inline callbacks are ignored.

## Configuration

```dotenv
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_ADMIN_IDS=123456789,987654321
TELEGRAM_WEBHOOK_SECRET=replace_with_a_random_secret

DB_SERVER=localhost
DB_PORT=3306
DB_DATABASE=travel_tour_bot
DB_USERNAME=travel_tour_bot
DB_PASSWORD=replace_with_a_strong_password
```

## Testing

Run the network-independent validation tests:

```bash
php tests/validators_test.php
php tests/webhook_test.php
```

Lint the PHP application:

```bash
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

GitHub Actions validates the project across PHP 8.0 through 8.4, installs dependencies, runs syntax checks and validation tests, rejects common tracked-secret patterns, and audits Composer dependencies.

## Data model

### `Data`

Stores the Telegram user ID, profile fields, profile-completion marker, and current conversation step.

### `Topics`

Stores the hierarchical tour/group catalogue. A root topic has parent/group value `0`; nested topics reference a parent topic ID.

### Legacy per-topic state

Memberships now use `TopicMemberships(user_id, topic_id, status)`, with a unique key, foreign keys and a topic/status index. Migration 001 transfers the old `topic<id>` values without dropping legacy columns. The runtime never creates or drops profile columns. Read the [migration guide](docs/MIGRATION.md) before upgrading; legacy columns become stale after new writes. Topic deletion requires administrator confirmation and removes all descendants transactionally.

## Security and privacy

The bot can process personal information, including phone numbers and national identifiers. Production deployments should use least-privilege database access, an explicit retention policy, restricted administrator access, and an appropriate user privacy notice.

Credentials are loaded from environment variables. `.env.example` contains placeholders only. Required Telegram webhook-secret verification is supported, and sensitive callback actions are restricted to configured administrators.

If any real token or database credential has previously appeared in this public repository or another public channel, rotate it. Removing a secret from the latest branch does not invalidate copies retained in Git history or external caches.

See [SECURITY.md](SECURITY.md) for deployment guidance.

## Scope and portfolio interpretation

This project demonstrates conversational workflow design, stateful onboarding, hierarchical catalogue navigation, approval operations, targeted communication, Telegram integration, and PHP/MySQL backend delivery.

It should **not** be presented as an automated payment system, real-time booking engine, or travel inventory platform. Payment and final operational fulfillment are outside the implementation in this repository.

## License

This repository is licensed under the GNU General Public License v3.0 only (`GPL-3.0-only`). See [LICENSE](LICENSE).
