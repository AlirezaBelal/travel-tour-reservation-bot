# Repository maintenance

`master` is the maintained public GPL-3.0-only source branch. **No GitHub Release, Tag or hosted deployment is required.**

- [Master protection ruleset](https://github.com/AlirezaBelal/travel-tour-reservation-bot/settings/rules/25541788): active, PR required, nine required CI checks, no force push/deletion. Verify settings again if workflows change.
- Work in small reviewable branches. Merge **only when the PR's exact SHA passes all required CI**, then delete the merged branch. Preserve `master` and history.
- [Dependabot](../.github/dependabot.yml) proposes reviewable weekly minor/patch updates for application Composer, quality tools and Actions. Major upgrades and security advisories need explicit review. No automatic merge.
- [Security](../SECURITY.md): privately report vulnerabilities, protect secrets and operator keys. Historical leaked Telegram tokens must be revoked by their owner; branch deletion does not revoke them. Close scanning alerts only with the correct verified resolution.
- [Issue tracker](https://github.com/AlirezaBelal/travel-tour-reservation-bot/issues) records outstanding work. Keep operational setup and sensitive data outside the repository.

No source PR should silently change payment semantics, authorization, lock order, migrations, data retention or outbox delivery. See [contributing](../CONTRIBUTING.md) and [CI acceptance](ACCEPTANCE.md).
