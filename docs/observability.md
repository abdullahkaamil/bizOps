# Observability

## Structured logs
Every request runs through `AssignRequestId`, which adds to the log context:
- `request_id` (correlation id, also returned as `X-Request-Id`),
- `tenant_id` (when tenancy is initialized),
- `user_id` (when authenticated).

Queued jobs carry the `tenant_id` (stancl `QueueTenancyBootstrapper`) and the
notification correlation id, so a job can be traced back to its request and tenant.

## Metrics & monitoring
- **Queue / failed jobs** — central `failed_jobs`; the tenant detail page surfaces
  **queue failures per tenant**.
- **Email failures** — every send is recorded in the tenant `email_logs` (sent /
  failed with error), so delivery is observable per tenant.
- **Database / storage** — tenant detail shows database size (`pg_database_size`),
  migration version, last activity, and user count.
- **Health checks** — `/up` (Laravel health endpoint); wire external uptime
  monitoring against it and the central domain.
- **Error tracking** — add Sentry/Bugsnag DSN in production (log driver ready).

## Audit
`activity_log` (tenant) captures business events with actor; central lifecycle and
support events go to `tenant_provisioning_logs`. See `docs/security.md`.
