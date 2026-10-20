# Final acceptance record — 2026-10-20

Status: **NOT READY for Release Candidate**. PR #4 must remain Draft.
The PR contains the exact final commit and CI run evidence; a proposed test is
not a passing result. No live webhook, infrastructure or real payment was changed.

## Review and correction

The review started from live head `1c209f2426a86677afd4c6bf060d45bed433b5b0`
and read ACCEPTANCE.md and DEPLOYMENT.md. Areas inspected include transaction
ownership/retries, per-tour seat locks/current reads, booking/payment transitions,
RBAC and queued permission checks, webhook receipts/rate limits, additive schema,
notification acknowledgement/leases and container credential boundaries.
This is a scoped code review, not an independent penetration test or proof of
absence of vulnerabilities.

**Found:** a long-running worker caught database disconnect exceptions and kept
reusing the disconnected PDO. After a database restart it could remain alive
without ever recovering service. **Correction:** exit on PDO exceptions and let
the configured Compose supervisor restart with a new connection. Startup errors
now emit only generic class/code information. Existing transactional outbox rows
and five-minute recovery leases are preserved; there is no schema change.

**Added executable acceptance:**

- `tests/worker_process_test.php`: kill a real subprocess after it claims a
  notification; prove another process respects its advisory lock; prove a fresh
  lease is not stolen; age only the test lease and recover using a new process.
  Persist a temporary service failure, respect backoff across processes, then
  deliver through a recovered synthetic transport. Runs on MySQL and MariaDB.
- `tests/container_restart.sh`: in a uniquely named disposable Compose project,
  require test mode, the allowlisted database and zero bookings/queue rows.
  Start the actual worker without sending Telegram requests, stop Apache,
  restart MySQL, verify supervisor restart and a new runtime DB connection,
  verify the worker remains alive, then restart Apache and check HTTP readiness.
- Stop web/worker writers before the CI backup, create a second Compose project
  with a verified empty new database volume, restore into that volume, restrict
  its runtime grants, start web and verify readiness plus booking/payment/history/
  outbox relations. The source volume is not the restore target.

All fixtures are synthetic. No token or secret is accepted through public logs,
commits, issues or chat. The authoring host has no PHP/Composer/MySQL/Docker;
runtime tests execute on GitHub Actions. A green final run is required before
these additions can be called verified. No local runtime success is claimed.

## Release gates still open

| Gate | Status and required owner action |
|---|---|
| Actual Telegram API, passenger/admin journeys and real notification delivery | **BLOCKED EXTERNAL**: owner supplies a separate test bot through an approved secret store and authorizes its staging endpoint. Never replace an existing webhook without explicit approval. |
| Authorized VPS/domain/TLS, proxy/firewall and server reboot | **BLOCKED EXTERNAL**: no infrastructure access or deployment authorization supplied. CI is disposable staging only. |
| Production/offsite encrypted backup, recovery timing and storage policy | **BLOCKED EXTERNAL**: owner infrastructure and isolated restore authorization required. |
| Historically exposed token revocation | **BLOCKED EXTERNAL**: owner verifies/revokes affected credentials with BotFather; clean current files do not revoke history. |
| Independent installation from published instructions | **NOT READY**: CI follows the documented commands, but an independent operator has not yet confirmed the instructions. |
| Owner review of residual risks and results | **NOT READY**: owner sign-off has not been recorded. |
| Merchant gateway and provider sandbox | **BLOCKED EXTERNAL / excluded from current payment capability**: manual/free payment and local signed simulator only. No live settlement or automatic refund. |

Manual-payment booking acceptance may proceed without a gateway, provided the
owner explicitly accepts this scope. Do not describe the local simulator as a
provider-hosted sandbox. Other limits remain in ACCEPTANCE.md: at-least-once
delivery, one booking identity per user/tour, CSV limit, Gregorian input and IRR.

## Authorized live test sequence

After owner approval, create a separate test bot and isolated database/Compose
project with fabricated identities. Keep secrets in restricted environment files
or the owner's approved secret store. Follow DEPLOYMENT.md for HTTPS and inspect
the endpoint before explicitly running the webhook registration helper.
Exercise traveler onboarding, published tour discovery, party reservation,
notification, booking history and cancellation; exercise manager tour edits,
payment confirmation, approval/rejection, capacity bounds, role revocation,
reports and private coordination. Record identifiers/timings privately and put
only redacted outcomes in the PR. Rehearse temporary worker/network outage,
server restart, backup and restore into a separate volume. Owner reviews results
and residual risks before removing Draft status or approving an RC.
