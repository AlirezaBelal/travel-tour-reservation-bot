# Repository maintenance and source release checklist

This is a public GPL-3.0-only, source-only PHP project. These tasks do **not**
authorize creation of a live bot, hosting, real webhook or remote database.

## Baseline verified on 2026-10-21

- Default branch: master.
- [Reviewed commit ef5a1c2](https://github.com/AlirezaBelal/travel-tour-reservation-bot/commit/ef5a1c2a12e0270fb98ecb926897e05fe09b43ff).
- [CI on that exact commit](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/runs/39572108378): 9/9 successful jobs.
- 21 non-default branches were individually associated with **merged** pull
  requests at this baseline. Recheck each branch and its head before deletion.
- Zero open pull requests and zero GitHub Releases at that baseline.
- This file records **instructions**, not completed GitHub administration.

## 1 — Protect master (administrator action)

In GitHub: Settings → Rules → Rulesets → New ruleset → New branch ruleset.
Target master, then enable **Active** enforcement.

Recommended:
- Prohibit force pushes and deletion of master.
- Require a pull request and passing checks before merge. For a solo maintainer,
  do not require an independent approval that cannot be supplied.
- Select the current real check names after confirming them in a PR:
  Static analysis and quality toolchain; PHP 8.0, 8.1, 8.2, 8.3, 8.4;
  MySQL 8.0 migration and concurrency; MariaDB 10.11 migration and
  concurrency; Clean container install and restore.
- Consider requiring a branch up-to-date with master when feasible.
- Limit bypass exceptions to true emergencies. Do not bypass failed checks.

**Verify enforcement by testing a harmless PR**. A written recommendation
or an inactive ruleset is not effective branch protection.

## 2 — Remove only merged topic branches

Open the [Branches page](https://github.com/AlirezaBelal/travel-tour-reservation-bot/branches).

For EACH branch: verify its associated PR says **Merged**, no unmerged wanted
commits remain, no worktree or collaborator still needs it, and it is not
master. Delete confirmed stale branches using the GitHub UI. Do not bulk
delete unknown refs and never force-push or rewrite history.

Deleting an already-merged source branch does not delete its PR or merge
commit on master. Keep verified release tags and intentional maintenance
branches. Refresh the branch list to confirm only intended refs remain.

## 3 — Keep dependencies and security maintained

The tracked Dependabot configuration proposes weekly PRs for the application
Composer dependencies, separate development quality tools, and GitHub Actions.
These are **reviewable updates**, never automatic merges. Review changes,
keep pinned complete Action SHAs, and require the full CI matrix. Independently
review Docker base-image digests and migration compatibility.

In Settings → Security, enable Dependabot alerts/security updates and private
vulnerability reporting if the repository/account supports them. Never include
a real secret in a public issue.

If an actual historical Telegram token was public, the owner must rotate or
revoke it via BotFather and update operator-managed credentials/webhooks.
Deleting a Git branch cannot revoke a leaked token.

## 4 — Optional tagged source release

Do not choose a version merely to increase a repository hygiene score.
When ready to make a public support/compatibility commitment:

1. Record the intended master commit and verify its own nine CI jobs succeed.
2. Review release notes, migration/backup requirements, GPL license and
   current dependencies; confirm the secret checks passed.
3. In GitHub Releases → Draft a new release, create a tag at that verified SHA.
   Describe it as **source-only/self-hosted**, not production-certified.
4. Review generated archives; do not attach runtime configuration, database
   dumps, tokens, encryption keys or real data.
5. Keep future release decisions tied to their own commit and CI evidence.

Reference [source snapshot notes](RELEASE_NOTES.md) and
[release acceptance requirements](ACCEPTANCE.md).

## Final readiness

Repository is tidy when the ruleset is active and enforced, outdated branches
have been reviewed and removed, current master CI is green, documentation is
current, and relevant historical credentials have been revoked by their owner.
A green synthetic CI does not certify any operator deployment.
