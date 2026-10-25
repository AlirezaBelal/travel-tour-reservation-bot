# Product scope

Travel Tour Reservation Bot is a self-hosted reservation tool for independent tour
operators serving Persian-speaking travelers in private Telegram chats. It brings
tour inventory, group requests, administrator review and notifications into one
bot. The operator owns the bot, database, hosting and traveler-data policy.

## Core journeys

| User | Journey and outcome |
|---|---|
| Traveler | Start → profile → find a published tour → view price/dates/capacity → choose 1–20 seats → confirm request → track status/history → cancel before departure |
| Operator | Create a draft tour → set dates/price/capacity → publish → review requests and manual payments → approve/reject → report capacity → complete ended bookings |
| Administrator | Assign scoped roles, review notification failures, send controlled messages and export reports without traveler identity columns |

Pending bookings hold seats; rejection and cancellation release them. Booking
changes, history and notification enqueueing commit together. Actual delivery
depends on the running worker and Telegram. Category membership is not required.

Name is required for booking. The operator may additionally require the account's
own Telegram-shared phone and a checksum-valid national code using
`REQUIRE_VERIFIED_PROFILE=true` (default false for existing-installation compatibility).
Identifiers are encrypted using the operator's key. This is not a government
identity check; operators must explain collection, retention and deletion.

## What this distribution includes

The complete PHP/MySQL source, Persian traveler/admin interface, Docker Compose,
versioned migrations, synthetic automated tests, English installation guides
and GPL-3.0-only license. `master` is the source entry point. Future changes
use focused branches and PRs; the original completion PR is closed.

There is no hosted bot, hosting subscription, merchant account, customer support
SLA or production certification included. Report reproducible source defects
through GitHub Issues without personal data or secrets; follow SECURITY.md for
security reports. Hosting and account administration remain the operator's responsibility.

## Limits to consider before installation

| Area | Current contract |
|---|---|
| Payments | Free booking/manual confirmation; no real gateway or automatic refund |
| Rebooking | One booking identity per traveler/tour, including after cancellation; no automatic same-tour rebooking |
| Group members | Seat count only; individual companion identities are not collected |
| Delivery | Durable outbox and retries; at least once, with possible duplicate delivery after a send/acknowledgement crash |
| Reporting | CSV limited to 5,000 rows, without traveler identity columns |
| Dates and money | Gregorian date entry; configurable display timezone; UTC storage; integer IRR |
| Operations | Own bot token, HTTPS hosting, MySQL, worker, encryption key and backup/restore policy required |

Install using the [quick start](../README.md#quick-start).
Read [deployment/recovery](DEPLOYMENT.md) and [migration](MIGRATION.md) before
upgrading existing data. [Acceptance gates](ACCEPTANCE.md) distinguish repeatable
synthetic checks from operator-specific live acceptance. Source publication does
not promise that a particular Telegram account, server or backup is operational.
