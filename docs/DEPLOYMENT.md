# Container installation and operations

This development branch is not a production-ready booking engine. It includes transactional group bookings and a durable queue; live Telegram,
VPS and merchant-provider acceptance remain external gates.

## Fresh isolated installation

Install Docker Engine and the Compose plugin. Copy `.env.example` to `.env`, set
all credentials, use distinct database user/root passwords, set a random webhook
secret (`openssl rand -hex 32`), and keep `.env` out of Git. Then:

```bash
docker compose build app
docker compose run --rm app php bin/install.php
docker compose up -d
curl -f http://127.0.0.1:8080/health.php
```

The database has no host port. HTTP binds only to the host's loopback interface.
Only `public/` is exposed by Apache; application source, dependencies, migrations,
credentials and backups must remain outside the document root. The health endpoint
returns a generic status and checks configuration, connectivity and migration 002.
It does not verify the Telegram token with Telegram.

Create a bot using Telegram's BotFather and keep the token private. On a Linux VPS,
place an HTTPS reverse proxy in front of `127.0.0.1:8080`, using a valid certificate
for your domain. Register `https://YOUR_DOMAIN/index.php` with Telegram's
`setWebhook`, supplying `secret_token` equal to `TELEGRAM_WEBHOOK_SECRET` and
`allowed_updates` containing `message` and `callback_query`. Supply token/secret
through a protected operator script, not Git, chat messages or shared command logs.
This repository does not configure a real domain, TLS certificate or live webhook.
The bot is used through private Telegram chats. A public repository does not run a
hosted bot; every operator needs their own token, database and HTTPS endpoint.

## Upgrade

Read [MIGRATION.md](MIGRATION.md) before upgrading an existing installation.
Stop writers, take and rehearse a backup, build the reviewed commit, apply
`php bin/migrate.php` using a maintenance container, then restart the app.
Do not delete the database volume. `docker compose down -v` deletes stored data;
that command is reserved for disposable CI/development environments.

## Backup and restore

Use `mysqldump --single-transaction --no-tablespaces` with an appropriately
privileged account and credentials in a protected client option file. Encrypt and
restrict backup files; profiles contain personal information. Test restoration in
an isolated database before trusting a backup. Stop writers during a coordinated
application/database rollback. A restore to an existing database overwrites data
and requires operator authorization. CI demonstrates dump/restore of synthetic
fixtures in a disposable container; it does not validate your storage, encryption,
retention policy or production recovery objectives.

## Troubleshooting

- HTTP 403 at the webhook: secret missing, mismatched or outside the documented
  32–256 character format. Register the same secret with Telegram.
- HTTP 405: only POST is supported.
- HTTP 413: webhook payload exceeds one MiB.
- Health 503: check environment, database connectivity and migration version.
- No response in a group or to an edited message: those updates are intentionally ignored.
- Missing memberships after upgrade: keep traffic paused and inspect migration
  results privately; do not drop legacy columns or bypass foreign keys.
- Review `docker compose ps` and `docker compose logs --tail=100 app db` in a
  restricted terminal. Do not post logs containing credentials or personal data.

Composer dependencies and PHP/Composer container bases are pinned to the versions
resolved by CI. Update pins deliberately and rerun audit/build/acceptance checks;
pinning does not replace security maintenance. OS packages still come from the
base distribution repositories at build time.
VPS/TLS/live-Telegram operation has not been verified by the automated tests.

## Worker and retention

`docker compose up -d` starts the app, database and worker. Check all three using
`docker compose ps`. `docker compose logs --tail=100 worker` reports generic class/code
errors. Do not run multiple bots with the same token or database unintentionally.
The worker has a MySQL dispatch lock, a five-minute lease timeout, conservative
send pacing, and schedules reminders, payment-hold expiration and retention.
Automatic profile cleanup defaults off. Enable `RETENTION_ENABLED=true` only after
approving the policy; set `RETENTION_DAYS` (30–3650, default 180) deliberately and document the policy
for your travelers. Remove personal data from backups according to your own
secure rotation policy; deleting an account does not rewrite old offline backups.

Create a dedicated maintenance DB account for schema changes; the web/worker
account needs normal DML, not ALTER/DROP privilege. Limit access to AuditLog and
outbox payloads, apply database/storage encryption and restrict logs/backups.
The app health endpoint checks schema/connectivity, not worker liveness; monitor
worker process health and pending/failed queue counts separately.

For local payment simulation in disposable data only, set PAYMENT_SANDBOX_ENABLED=true
and a distinct random PAYMENT_SANDBOX_SECRET. Run
`docker compose run --rm app php bin/sandbox-payment.php ORDER_REF TRANSACTION paid`
using a synthetic pending order. Restore the enable flag to false afterwards.
This does not test any actual gateway or merchant settlement.

`docker compose run --rm app php bin/worker.php --health` checks worker dependencies
without sending to Telegram. It is a diagnostic, not proof a background process is
running. Access to profiles/reports/exports is rechecked again at queued delivery.
