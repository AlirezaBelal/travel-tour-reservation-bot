# Security

## Secrets

Do not commit live credentials to this repository.

Runtime values such as the Telegram bot token, database credentials, administrator IDs, and webhook secret belong in `.env` or the deployment platform's secret store. The tracked `.env.example` file contains placeholders only.

If a real credential has ever appeared in a public commit, deployment log, screenshot, or chat, rotate or revoke it. Removing the value from the current branch does not invalidate copies that may remain in Git history or external caches.

## Telegram webhook

The application supports Telegram's webhook secret header through `TELEGRAM_WEBHOOK_SECRET`.

When enabled:

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

The current runtime preserves a legacy dynamic-column model for per-topic approval state. Treat schema-management commands as administrator-only operations and apply explicit migrations to existing deployments.

## Logging

Keep error messages generic. Never log the Telegram bot token, database password, webhook secret, raw authorization values, or user personal data unless there is a documented operational requirement and appropriate access control.

## Reporting

For a privately discovered vulnerability, contact the repository owner directly rather than publishing credentials, personal data, or exploit details in a public issue.
