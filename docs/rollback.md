# Rollback

How to back out a bad release. The strategy relies on **expand-and-contract**
migrations (see [deployment.md](deployment.md)): because a new release only adds
schema in its `expand` phase, the previous code keeps working against the new
schema, so rolling back code is safe without rolling back the database.

## Decision: roll back or fix forward?

- **Roll back** (default) when the release is broadly broken or the cause is not
  obvious within a few minutes.
- **Fix forward** only when the fix is small, understood, and already tested, and
  a hot patch is faster than a rollback.

## Rolling back code

1. Check out the **previous release** and re-run the deploy:
   `git checkout <prev-tag> && ./scripts/deploy.sh` (rebuilds vendor/assets,
   re-warms caches, restarts workers onto the old code).
2. `php artisan app:health-check` and hit `GET /up`.
3. Confirm smoke logins and that the queue is draining.

No database change is required: the new (expanded) schema is a superset the old
code tolerates.

## The one dangerous case: contract migrations

A `contract` migration (dropping a column/table) is destructive and **cannot be
undone by redeploying old code** — the data is gone. Rules:

- Never ship a `contract` migration in the same release that removes its last
  reader. Contract only once a prior release proved nothing reads the old shape.
- If a release containing a `contract` migration must be rolled back, restore
  from backup rather than relying on code rollback:
  - **Single tenant** — `App\Central\Actions\RestoreTenant` from the latest
    pre-release `*.json.enc` snapshot (see [backups.md](backups.md)).
  - **Central schema** — restore the central database from its snapshot.

## Tenant migration failures mid-release

If `tenants:migrate-batched` reported failures, the healthy tenants are already
on the new schema and fine. For the failed tenants:

1. Inspect `tenant_migration_runs` (`status = failed`, `error_class`,
   `error_message`) for the release.
2. Fix the cause (data issue, missing extension, etc.).
3. Retry only the failures:
   `php artisan tenants:migrate-batched --release=<RELEASE> --retry-failed`.
4. If a tenant cannot be migrated, restore just that tenant from its pre-release
   backup and exclude it from the release until fixed.

## After any rollback

- Announce the rollback and the reason.
- Open a follow-up to root-cause before re-attempting the release.
- Verify backups are still current before the next attempt.
