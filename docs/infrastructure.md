# Infrastructure

Reference for the services and configuration that make up a running BizOps
deployment. It is deployed **natively** (no Docker); step-by-step provisioning of
a fresh box is [`ubuntu-server-setup.md`](ubuntu-server-setup.md).

## Runtime

Standard LEMP-style stack on the host, PHP 8.3:

- **nginx** — TLS termination, serves `public/` (built assets), proxies PHP to
  php-fpm. One server block covers the apex **and** `*.domain` (Laravel routes by
  host). See the config in the setup runbook.
- **php-fpm 8.3** — runs the app. Extensions: `pdo_pgsql`, `pgsql`, `gd`, `zip`,
  `bcmath`, `mbstring`, `intl`, `curl`, `opcache`.
- **Build** — `npm ci && npm run build` produces `public/build`; `composer
  install --no-dev --optimize-autoloader` for vendor. Both run at deploy time
  (`scripts/deploy.sh`), not at request time.
- **OPcache** — set `opcache.validate_timestamps=0` in production (php.ini) and
  re-run `config:cache` + reload php-fpm on each deploy so new code is picked up.

## Services

| Role | How it runs | Scale | Notes |
|------|-------------|-------|-------|
| Web | php-fpm behind nginx | 1+ hosts | Stateless; scale by adding hosts |
| Queue worker | systemd `php artisan queue:work --tries=3 --max-time=3600` | 1+ | Central-pinned queue |
| Scheduler | cron `php artisan schedule:run` every minute | any host | `onOneServer` guards single-run |
| PostgreSQL | system service or managed | 1 (or managed) | Central db + per-tenant dbs; role needs `CREATEDB` |
| Redis | system service or managed | 1 (or managed) | Cache + sessions |

The systemd unit and cron entry are in the setup runbook. Object storage
(S3-compatible) is external; set `FILESYSTEM_DISK=s3` and the `AWS_*` keys.

## Configuration

- Runtime config comes from `.env` on the server (never committed);
  [`.env.example`](../.env.example) documents every key. Production values are
  listed in [`ubuntu-server-setup.md`](ubuntu-server-setup.md) §4.
- `php artisan app:validate-env` validates the config and is a hard gate in
  `scripts/deploy.sh`. In `production` it additionally requires `APP_DEBUG=false`,
  `https` `APP_URL`, persistent session/cache drivers, a non-`sync` queue, and
  the central-pinned queue connection.

## Scheduler jobs

Registered in [`routes/console.php`](../routes/console.php):

- `tenants:backup` — nightly encrypted logical backup of every tenant (01:00).
- `tasks:notify-due-soon` — hourly.
- `quotations:expire` — daily (02:00).

## Health & monitoring

- `GET /up` — framework health endpoint (used by the load balancer and smoke
  test).
- `php artisan app:health-check [--json]` — verifies database, cache, storage,
  and queue connectivity; non-zero exit on failure.
- Request correlation: `AssignRequestId` stamps `request_id`, `tenant_id`, and
  `user_id` onto the log context (see [observability.md](observability.md)).
- Wire an error tracker (e.g. Sentry) via its `*_DSN` env key.
