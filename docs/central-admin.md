# Central SaaS Administration

Operating the platform without mixing central and tenant concerns (Phases 4 + 20).
The console lives on the central domain (`dashboard.kaamil.test`), guarded by the
`central` middleware.

## Separation
Tenant models resolve to the **tenant** connection and cannot be queried in central
context (their tables don't exist in the central database) — this is a hard,
tested guarantee, not a convention.

## Tenant lifecycle & health
Per tenant: list, detail, provisioning timeline, status, suspend/reactivate,
license renew, **plan / trial / subscription** metadata, and **health metrics**
(database size, migration version, queue failures, last activity, user count).

## Feature flags
`tenants.features` (jsonb) holds per-tenant flags, **default-on**
(`Tenant::hasFeature` returns true unless a flag is explicitly `false`). Routes
gate behind the `feature:<name>` middleware — quotations is wired as the reference;
other modules follow the same one-line pattern.

## Staged deletion
Deletion is staged and reversible until the final purge:

```
active → suspended → archived → deletion_pending → deleted
```

- `ArchiveTenant` — suspended → archived.
- `RequestTenantDeletion` — archived → deletion_pending; **requires double
  confirmation** (accept + retype the tenant name) and starts a **retention
  window** (`purge_after`), giving an export/backup opportunity.
- `PurgeTenant` — deletion_pending → deleted; the **retention guard cannot be
  bypassed** (purging before `purge_after` throws). Drops the tenant database.

Every step is audited to the central `tenant_provisioning_logs`.

## Support access
`SupportSession` records an **audited, time-limited** support-access session — a
mandatory reason, an expiry, and the operator's identity. There is no silent
access; the record (and the in-app banner) make every session visible. Full
login-as-tenant is deferred; the audit + policy scaffold is in place.

## System announcements
Central `announcements` (with a `live` scope) are shared to every tenant user via
the Inertia middleware, so platform-wide notices reach all workspaces.
