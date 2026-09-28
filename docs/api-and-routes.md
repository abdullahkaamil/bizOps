# API & Route Conventions

## Domain split

Every route belongs to exactly one context, enforced by middleware (not a hard
`Route::domain` lock — the guards 404 on the wrong domain, which lets Wayfinder
emit same-origin **relative** URLs and avoids cross-origin XHR/CORS problems).

- **Central** (`central` → `EnsureCentralDomain`): 404s when tenancy is
  initialized (i.e. on a tenant subdomain). Lives on the central/admin domain
  (`dashboard.kaamil.test`).
- **Tenant** (`tenant` → `EnsureTenantDomain`): 404s when tenancy is **not**
  initialized (i.e. on the central domain). Lives on `{sub}.kaamil.test`.

Isolation is covered by `tests/Tenancy/TenantIsolationTest.php` (central routes
404 on tenant domains; tenant routes 404 on the central domain; unknown domains
404).

### Central routes (central domain only)

```
/admin/tenants                 admin.tenants.index / store
/admin/tenants/{tenant}        admin.tenants.show / destroy
/admin/tenants/{tenant}/*      lifecycle verbs (suspend, reactivate, archive,
                               request-deletion, purge, retry, renew, support)
/admin/tenants/{tenant}/plan   admin.tenants.plan (PUT — plan fields)
/admin/tenants/{tenant}/features  admin.tenants.features (PUT — feature flags)
/admin/announcements           admin.announcements.index / store / destroy
/login, /forgot-password, …    Fortify auth (central & tenant)
```

The console root (`/`) on the admin domain redirects to `/admin/tenants` — the
tenant list + announcements **are** the "system admin" surface (there is no
separate `/admin/system` page).

### Tenant routes (tenant domain only)

```
/dashboard   /customers   /tasks   /boards   /jobs   /workshop
/inventory   /quotations   /settings   /search   /users
```

**`/users`, not `/employees`** — the user-management module covers internal
employees **and** external customer-representative users, so `/users` is the
accurate umbrella (`/users`, `/users/invite`, `/users/{user}/role|department|
suspend|reactivate`). Internal-only lists are filtered within it.

## Explicit transition endpoints

Protected state transitions are **dedicated `POST /resource/{id}/verb` endpoints**,
one verb per transition — never a generic status setter. This is enforced by the
per-transition policies and guarded action classes (see
`docs/state-machines.md`).

Tasks are the exception: they use free movement across user-defined board columns.
`POST /tasks/{task}/move` takes a `column_id` (any column on the task's own board)
and an optional `position`; the task's status is derived from the destination
column's category. Board columns are managed by internal staff.

```
POST   /tasks/{task}/move           (body: column_id, position?)
POST   /boards/{board}/columns          POST /boards/{board}/columns/reorder
PUT    /columns/{column}                DELETE /columns/{column}

POST /jobs/{job}/start              POST /jobs/{job}/complete
POST /jobs/{job}/assign             POST /jobs/{job}/cancel   POST /jobs/{job}/reopen

POST /workshop/{ticket}/complete    POST /workshop/{ticket}/deliver

POST /quotations/{quotation}/send     POST /quotations/{quotation}/accept
POST /quotations/{quotation}/reject   POST /quotations/{quotation}/expire
POST /quotations/{quotation}/cancel
```

### Never: `PATCH /resource/{id}` with an arbitrary status

`PUT /resource/{id}` exists only for **field edits** (title, description,
priority, due date, line items, plan/feature fields) — it **cannot** change a
protected lifecycle status. Status only ever moves through the verb endpoints
above, each of which validates the current state and records history + an
activity-log entry. There is no endpoint that accepts a caller-supplied status
value for tasks, jobs, workshop tickets, or quotations.

The only PATCH routes are `PATCH /settings/profile` (own profile) and
`PATCH /users/{user}/role` (a single, policy-checked, audited field) — neither is
a lifecycle transition.
