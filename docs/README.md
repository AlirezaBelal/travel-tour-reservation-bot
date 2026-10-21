# Documentation / راهنمای مستندات

Start with the English or Persian README, then follow the guide for your task.
برای شروع، README فارسی را بخوانید؛ برای هر کار از راهنمای مربوط استفاده کنید.
The maintained installation source is `master`. Each operator runs their own bot.

| Task / کار | Guide / راهنما |
|---|---|
| Install from zero / نصب از صفر | [English Quick Start](../README.md#quick-start) · [راهنمای فارسی](../README.fa.md#نصب-از-صفر) |
| Understand product scope / امکانات و محدودیت‌ها | [Product](PRODUCT.md) |
| Use traveler/admin menus / کار با ربات | [English guide](../README.md#traveler-and-operator-guide) · [مسافر](../README.fa.md#استفاده-مسافر) · [مدیریت](../README.fa.md#مدیریت) |
| Operate, upgrade, back up and restore / عملیات و بازیابی | [Deployment](DEPLOYMENT.md) · [Migrations](MIGRATION.md) |
| Understand components and data flow / معماری | [Architecture](ARCHITECTURE.md) |
| Reproduce automated checks / اجرای تست‌ها | [Acceptance](ACCEPTANCE.md) |
| Fix bugs or contribute / مشارکت | [Contributing](../CONTRIBUTING.md) |
| Review changes and limits / تغییرات نسخه | [Changelog](../CHANGELOG.md) · [Source notes](RELEASE_NOTES.md) |
| Handle credentials and personal data / امنیت | [Security](../SECURITY.md) |

## First usable installation

1. Create your own Telegram bot using BotFather and configure private credentials.
2. Follow Quick Start to build, migrate and restrict database privileges.
3. Configure your HTTPS endpoint and register only your authorized bot's webhook.
4. Keep both web and worker running. Set the owner ID and create a draft tour in `/admin`.
5. Publish a synthetic free tour and exercise reservation, approval, notification
   and cancellation with test accounts before admitting travelers.
6. Review backup/restore, identity collection policy and manual-payment limitations.

Only the operator can validate their token, TLS, hosting, payment-review procedure
and storage policy. Automated checks and public source do not certify production.
No real payment gateway or automatic refund is included.

Development transcripts and superseded status reports are kept in Git/PR history.
These guides describe the source consumers install, not the original authoring session.

The [engineering quality review](QUALITY_REVIEW.md) records measured before/after
boundaries, validation evidence, scoring criteria and remaining debt. The current
[architecture](ARCHITECTURE.md) describes the actual composed runtime and transaction
contracts, including the deliberately retained compatibility paths.
