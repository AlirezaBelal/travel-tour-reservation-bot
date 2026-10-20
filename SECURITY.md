# Security

## Secrets

Do not commit live credentials to this repository.

Runtime values such as the Telegram bot token, database credentials, administrator IDs, and webhook secret belong in `.env` or the deployment platform's secret store. The tracked `.env.example` file contains placeholders only.

If a real credential has ever appeared in a public commit, deployment log, screenshot, or chat, rotate or revoke it. Removing the value from the current branch does not invalidate copies that may remain in Git history or external caches.

## Telegram webhook

The application requires `TELEGRAM_WEBHOOK_SECRET` containing 32–256 letters, digits, underscores or hyphens. Missing, weak or mismatched secrets fail closed. Only POST requests are accepted.

For every installation:

1. Register the Telegram webhook with the same secret token.
2. Serve the webhook over HTTPS only.
3. Keep the secret outside source control.
4. Requests without the matching `X-Telegram-Bot-Api-Secret-Token` header are rejected.

Sensitive callback operations such as topic administration, segmented sends, approval, and deletion are additionally restricted to configured administrator Telegram IDs.

## Personal data

This workflow can collect personal information such as name, phone number, national identifier, birth date, and gender. Deployments should collect only information that is actually required, restrict database access, define a retention policy, and provide an appropriate privacy notice to users.

Do not use production personal data in tests, screenshots, issues, or public repository examples.

## Database

Use a dedicated database account with only the permissions required by this application. Do not reuse a root or hosting-control-panel database credential.

The runtime uses relational topic memberships and does not execute ALTER TABLE. Run the additive legacy migration with a separate maintenance account while writers are stopped. Retained legacy columns become stale after new writes, so a code-only rollback is unsafe. See [migration guidance](docs/MIGRATION.md).

The bot only processes private conversations belonging to the update sender. Group/inline/edited updates are ignored; callback data is limited to 64 bytes. New product operations use ownership/RBAC checks, receipted updates, per-minute ingress limits, persistent forms, transactional outbox events and account anonymization/retention. These controls do not establish live production acceptance; see docs/ACCEPTANCE.md.

## Logging

Keep error messages generic. Never log the Telegram bot token, database password, webhook secret, raw authorization values, or user personal data unless there is a documented operational requirement and appropriate access control.

## Reporting

For a privately discovered vulnerability, contact the repository owner directly rather than publishing credentials, personal data, or exploit details in a public issue.

## Product-specific controls and limits

New bookings require only a leader name. Existing legacy profile fields remain
nullable and are removed when the account is erased. Active bookings must be
cancelled before erasure. Closed financial/count records remain without account
linkage; transaction references are hashed. Personal profile view is limited to
manager/owner, audited, and queued personal messages are purged with the subject.
Previously delivered Telegram messages and offline backups require operator policy.

CSV exports exclude traveler profile/Telegram-ID columns, neutralize spreadsheet
formulas, are permission-gated/audited, and recheck export permission before delivery.
Temporary CSV files have restrictive permissions and are deleted after sending.
Never expose the outbox or its payloads through a public endpoint.

The payment sandbox endpoint is disabled by default and verifies HMAC, freshness,
order, amount, expiration and transaction uniqueness. It is a local simulator only.
No real gateway or automatic refund is enabled. Keep simulator keys distinct from
webhook/token/database secrets and disable the endpoint outside disposable tests.

Historical token-pattern matches were discovered without copying their values into
reports. The operator must revoke/rotate any real exposed token in BotFather, then
update the secret store and webhook. A Git deletion/history rewrite cannot revoke
a credential. Do not rewrite repository history as a substitute for rotation.

Automatic profile anonymization is opt-in (`RETENTION_ENABLED=false` by default),
so an upgrade does not silently delete inactive legacy profiles. Enable it only
after policy review/notice. Explicit confirmed account deletion remains available.
Terminal queue payloads have a seven-day retention, ingress counters one day, and
update receipts 30 days. In-flight/already-delivered Telegram messages are outside
a database purge. Environment-configured owner IDs must be removed by the operator
when an owner relationship ends; account deletion cannot rewrite deployment secrets.

CI secret-pattern guards scan tracked documentation as well as source and report
filenames only, never matching credential values. They are pattern checks, not
proof that every possible secret format has been detected.

Compose forwards only an explicit environment allowlist to app/worker. Root DB
credentials belong only to the isolated, explicitly invoked maintenance service
and the private database container. Run `bin/restrict-runtime.php --confirm` from
maintenance after initialization to reduce runtime grants to schema DML. CI proves
that the web process lacks the root secret, can operate, and cannot CREATE a table.
