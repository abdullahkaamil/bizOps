# Release Checklist

Run through this before promoting a release to production. Every item must be
green.

## Pre-flight

- [ ] **CI green** — `tests.yml` passed on the release commit (Pint, PHPStan,
      vue-tsc, build, full Pest suite).
- [ ] **Staging tested** — the release commit was deployed to staging via `scripts/deploy.sh`
      and smoke-tested.
- [ ] **Database backup current** — a fresh `tenants:backup` (and central db
      snapshot) exists and its restore path has been exercised (see
      [backups.md](backups.md)).
- [ ] **Migrations reviewed** — every new tenant/central migration is
      backward-compatible (expand-and-contract); no long table locks.
- [ ] **Rollback plan written** — the previous release tag is known and the
      rollback steps ([rollback.md](rollback.md)) apply to this release's
      migrations.
- [ ] **Queue compatibility confirmed** — no job payload/class was renamed or
      removed in a way that breaks jobs already enqueued by the old release.
- [ ] **Environment variables validated** — `app:validate-env` passes against the
      production config; new keys added to `.env.example`.
- [ ] **DNS & certificates correct** — apex, `dashboard.*`, and wildcard
      `*.domain` resolve; TLS cert covers them and is not near expiry.
- [ ] **Smoke-test users available** — a central admin and at least one tenant
      login are ready for post-deploy verification.
- [ ] **Monitoring active** — error tracking and queue-depth dashboards are up and
      being watched.

## Deploy

- [ ] Pull the release commit onto the web/worker host(s): `git checkout <tag>`.
- [ ] Run `RELEASE=<tag> SMOKE_URL=https://<central>/up ./scripts/deploy.sh`.
- [ ] Confirm all 8 steps completed (env → central migrate → tenant migrate →
      worker restart → cache warm → health → smoke → monitor).

## Post-deploy

- [ ] `app:health-check` is green.
- [ ] Central + tenant smoke logins succeed.
- [ ] Queue is draining (no growing backlog, no spike in failed jobs).
- [ ] No error-rate spike for 15 minutes.
- [ ] `tenant_migration_runs` shows every live tenant `completed` for this
      release (investigate/retry any `failed`).

## If something is wrong

Follow [rollback.md](rollback.md). Prefer rolling back over hot-fixing forward
unless the fix is trivial and already tested.
