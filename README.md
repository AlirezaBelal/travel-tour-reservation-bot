# Travel Tour Reservation Bot

[![CI](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml)

A PHP/MySQL Telegram workflow for traveler onboarding, hierarchical tour-group discovery, participation requests, administrator approval, and targeted member communication.

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
- Optional Telegram webhook-secret verification
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
```

Expose `index.php` through HTTPS and register that endpoint as the Telegram webhook.

For stronger webhook authentication, configure `TELEGRAM_WEBHOOK_SECRET` and register the same secret with Telegram.

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
```

Lint the PHP application:

```bash
find . -type f -name '*.php' -not -path './vendor/*' -not -path './medoo/vendor/*' -print0 | xargs -0 -n1 php -l
```

GitHub Actions validates the project across supported PHP versions, installs dependencies, runs syntax checks and validation tests, rejects common tracked-secret patterns, and audits Composer dependencies.

## Data model

### `Data`

Stores the Telegram user ID, profile fields, profile-completion marker, and current conversation step.

### `Topics`

Stores the hierarchical tour/group catalogue. A root topic has parent/group value `0`; nested topics reference a parent topic ID.

### Legacy per-topic state

The original runtime represents per-user topic status with dynamically created `topic<id>` columns on `Data`. This behavior is preserved for compatibility with the existing code path. It works for the original operational model but is not the preferred schema for a large multi-tenant system; a normalized `user_topic_status` relation is the natural future migration.

## Security and privacy

The bot can process personal information, including phone numbers and national identifiers. Production deployments should use least-privilege database access, an explicit retention policy, restricted administrator access, and an appropriate user privacy notice.

Credentials are loaded from environment variables. `.env.example` contains placeholders only. Optional Telegram webhook-secret verification is supported, and sensitive callback actions are restricted to configured administrators.

If any real token or database credential has previously appeared in this public repository or another public channel, rotate it. Removing a secret from the latest branch does not invalidate copies retained in Git history or external caches.

See [SECURITY.md](SECURITY.md) for deployment guidance.

## Scope and portfolio interpretation

This project demonstrates conversational workflow design, stateful onboarding, hierarchical catalogue navigation, approval operations, targeted communication, Telegram integration, and PHP/MySQL backend delivery.

It should **not** be presented as an automated payment system, real-time booking engine, or travel inventory platform. Payment and final operational fulfillment are outside the implementation in this repository.

## License

See [LICENSE](LICENSE) for the repository's license terms.
