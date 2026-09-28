# Security

Controls in place for handling real customer data. Most are enforced in code and
covered by `tests/Tenancy/SecurityTest.php`.

| Control | Where |
|---|---|
| CSRF protection | Laravel web middleware (default) |
| Session security / secure cookies | encrypted cookies; sessions in the tenant DB; host-scoped per subdomain |
| Rate limiting / brute-force | Fortify `login` limiter (5/min per email+ip), `two-factor`, `passkeys` |
| Password policy | `Password::defaults` — 12+ mixed-case, numbers, symbols, uncompromised (production) |
| MFA (optional) | Fortify two-factor |
| Email verification | Fortify `verified` middleware on the app |
| Signed URLs | document downloads use temporary signed routes (tested to expire) |
| Private file storage | `local` disk, tenant-prefixed paths; never public |
| MIME validation | `image` + `mimetypes` rules validate decoded content, not extension |
| File-size limits | per-upload `max:` rules (photos 20 MB, signatures 2 MB) |
| Image metadata stripping | GD re-encode on upload strips EXIF/metadata |
| Content-Security-Policy + headers | `SecurityHeaders` middleware (CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS on TLS) |
| SQL-injection protection | Eloquent / query bindings throughout |
| Mass-assignment protection | `$guarded`/`#[Fillable]`; sensitive fields `#[Hidden]` |
| Authorization | per-model policies, server-authoritative; every controller `authorize`s |
| Tenant isolation | database-per-tenant; cross-tenant file & DB access tested to fail |
| Secret management | `.env` (never committed); backups encrypted with the app key |
| Audit logging | `activity_log` (tenant) + `tenant_provisioning_logs` (central) — see below |
| Dependency scanning | `composer audit` / `npm audit` in CI (recommended) |

## Audited events
Login / logout / failed-login (`auth.*`, no credentials logged), user invitations,
**role changes**, customer changes, task/job/workshop transitions, stock movements
(the ledger itself), quotation status changes, tenant suspend/reactivate/archive/
deletion/purge, and **central support access**. Each entry records the actor and is
tenant-scoped (or, for central events, keyed by tenant id).

## Tested guarantees
Cross-tenant file access fails · cross-tenant DB access fails · rate limiting works
· sensitive fields never serialized · signed URLs expire · audit entries include
actor + tenant.
