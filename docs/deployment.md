# Deployment & Release

How a release reaches production safely and how to roll it back.

## Environments

| Environment | Purpose | Notes |
|-------------|---------|-------|
| Local | Development | Valet + Postgres/Redis, `*.kaamil.test` |
| CI | Automated gates | `.github/workflows/tests.yml` — Pint, PHPStan, vue-tsc, build, Pest |
| Staging | Pre-production rehearsal | Same native setup + release process as production |
| Production | Live tenants | Managed Postgres/Redis/S3 recommended |

## Topology

Deployed **natively** (no Docker) — nginx + php-fpm on the box, with the queue
worker and scheduler as OS services. First-time server provisioning is the
step-by-step runbook in [`ubuntu-server-setup.md`](ubuntu-server-setup.md).

```
nginx (one server block: example.com + *.example.com → public/)
├── php-fpm                 (serves the app; Laravel routes by host)
├── Queue worker           (systemd: php artisan queue:work, scale horizontally)
├── Scheduler              (cron: php artisan schedule:run every minute)
├── PostgreSQL             (central db + one db per tenant)
├── Redis                  (cache + sessions)
├── Storage                (local disk with signed URLs; or S3-compatible)
└── Monitoring / error tracking
```

A single host runs all roles; scale by adding worker hosts (or more `queue:work`
services) and putting Postgres/Redis/storage on managed services. Nothing about
the app assumes containers.

### Queue is pinned to the central database

`DB_QUEUE_CONNECTION=pgsql` keeps the `jobs` table on the central connection.
`QueueTenancyBootstrapper` re-initializes the correct tenant per job, so a worker
that last booted into tenant A still reads jobs from the central store, not A's
database. `app:validate-env` enforces this.

## DNS & certificates

- **Central domain** — apex `A`/`AAAA` record (e.g. `example.com`).
- **Admin console** — `dashboard.example.com`.
- **Wildcard tenant subdomains** — `*.example.com` so every tenant resolves.
- **Certificate** — a wildcard TLS cert covering the apex and `*.example.com`.
- Custom per-tenant domains are a later addition (not in this release).

## Release process

Driven by [`scripts/deploy.sh`](../scripts/deploy.sh) — pull the new code first,
then run it. Steps, in order (all inside the script):

1. **Maintenance window** — `php artisan down` while dependencies/assets/schema change.
2. **Dependencies & assets** — `composer install --no-dev` and `npm ci && npm run build`.
3. **Central migrations** — `php artisan migrate --force`.
4. **Tenant migrations in batches** — `php artisan tenants:migrate-batched
   --release=$RELEASE --batch-size=50` (see below).
5. **Warm caches** — `config:cache`, `route:cache`, `event:cache`, `view:cache`.
6. **Restart workers** — `php artisan queue:restart` (they reboot onto new code).
7. **Validate & health** — `php artisan app:validate-env` then `app:health-check`; `php artisan up`.
8. **Smoke test** — `curl --fail $SMOKE_URL` against the central `/up` endpoint.

```bash
git pull --ff-only
RELEASE=$(git rev-parse --short HEAD) SMOKE_URL=https://example.com/up ./scripts/deploy.sh
```

Because migrations are expand-and-contract (below), old code that briefly served
during the pull stays compatible with the new schema.

Any failing step aborts the release. The script is idempotent — safe to re-run
after fixing the cause.

## Tenant migrations at scale

`tenants:migrate-batched` migrates every live tenant database in controlled,
resumable batches and records per-tenant state in `tenant_migration_runs`
(one row per tenant per `--release`):

- **Batching** — `--batch-size` controls how many tenants migrate per batch,
  bounding load and lock pressure.
- **Per-tenant state** — each tenant's run is `pending → running → completed |
  failed`, with attempt count, applied-migration count, and any error captured.
- **Retry** — `--retry-failed --release=$RELEASE` re-runs only the tenants whose
  last attempt for that release failed; healthy tenants are untouched.
- **Skips torn-down tenants** — `deleted`, `failed`, and `deletion_pending`
  tenants are excluded (no live database to migrate).
- **Dry run** — `--pretend` prints the SQL without recording state.

### Expand-and-contract

Because in-flight queue workers (and, in a multi-host setup, hosts not yet
updated) may run old code against the new schema, tenant migrations **must be
backward compatible**. Split breaking schema changes across releases:

1. **Expand** — add the new column/table (nullable / with default). Deploy.
2. **Migrate data & code** — backfill; ship code that writes both old and new.
3. **Contract** — once no code reads the old shape, drop it in a later release.

Avoid long-held table locks (e.g. adding a non-null column with a default on a
large table) — prefer add-nullable-then-backfill.

## Rollback

See [rollback.md](rollback.md). In short: check out the previous release and
re-run the deploy (`git checkout <prev-tag> && ./scripts/deploy.sh`); because
migrations are expand-and-contract the old code still runs against the new schema.
Only `contract` migrations are destructive — never combine a `contract` migration
with the release that removes its last reader.

## Release checklist

See [release-checklist.md](release-checklist.md).
