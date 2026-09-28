# Decision Register

Phase 0 — Discovery. Converts ambiguous client wording into explicit business rules.

- **Default recommendation** = the MVP default I propose.
- **Decision** = current adopted rule. Per the standing directive, ambiguous items are decided with the MVP default (delegated); the client may override any row later, which will bump its date and cascade to the impacted modules.
- **Status**: `Decided (MVP)` = built now with this rule · `Deferred` = out of scope for MVP, revisit before production.

_Last updated: 2026-07-26._

## Tenancy

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| T1 | Tenant = one legal company or one branch? | One legal company | One legal company | 2026-07-26 | Tenancy, Employees, Inventory | Decided (MVP) |
| T2 | Multiple branches per tenant? | No — single implicit branch; keep a `branch_id` seam nullable for later | No (single branch) | 2026-07-26 | Inventory, Jobs, Employees | Decided (MVP) |
| T3 | Custom domains? | No — subdomain identification only | No | 2026-07-26 | Tenancy | Deferred |
| T4 | One user in multiple tenants? | No — users are tenant-local (per-tenant DB) | No | 2026-07-26 | Auth, Employees | Decided (MVP) |
| T5 | Central single sign-on? | No — tenant-local auth; central admins separate | No | 2026-07-26 | Auth | Deferred |

## CRM (Customers)

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| C1 | Multiple contacts per customer? | Yes | Yes (has-many contacts) | 2026-07-26 | CRM | Decided (MVP) |
| C2 | Multiple service addresses per customer? | Yes | Yes (has-many addresses, one default) | 2026-07-26 | CRM, Jobs, Workshop | Decided (MVP) |
| C3 | Tax details required? | Optional (nullable tax id / rate) | Optional | 2026-07-26 | CRM, Quotations | Decided (MVP) |
| C4 | Inactive customers retained? | Yes — `is_active` flag + soft delete | Retained (soft delete) | 2026-07-26 | CRM | Decided (MVP) |

## Tasks

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| K1 | Multiple assignees? | Single primary assignee for MVP; pivot-ready for multi | Single assignee | 2026-07-26 | Tasks | Decided (MVP) |
| K2 | External users upload files? | No — external reps review/approve only | No | 2026-07-26 | Tasks, Documents | Decided (MVP) |
| K3 | External users see internal comments? | No — internal comments hidden from externals | No | 2026-07-26 | Tasks | Decided (MVP) |
| K4 | Rejection returns to `todo` or `in_progress`? | `in_progress` (assignee resumes) | → `in_progress` | 2026-07-26 | Tasks | Decided (MVP) |
| K5 | Reopen completed tasks? | No — terminal; admin-only override later | No | 2026-07-26 | Tasks | Decided (MVP) |
| K6 | Due dates required? | Optional | Optional | 2026-07-26 | Tasks | Decided (MVP) |
| K7 | Priorities required? | Optional — enum, default `normal` | Optional (default normal) | 2026-07-26 | Tasks | Decided (MVP) |
| K8 | Recurring tasks? | Out of scope | — | 2026-07-26 | Tasks | Deferred |

## Jobs

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| J1 | Reschedule jobs? | Yes | Yes | 2026-07-26 | Jobs | Decided (MVP) |
| J2 | Cancel jobs? | Yes — with reason, terminal | Yes (with reason) | 2026-07-26 | Jobs | Decided (MVP) |
| J3 | Multiple technicians? | Single primary technician for MVP; pivot-ready | Single technician | 2026-07-26 | Jobs, Employees | Decided (MVP) |
| J4 | Who signs the job? | On-site customer signs (technician optional) | Customer signature | 2026-07-26 | Jobs | Decided (MVP) |
| J5 | Signature mandatory? | Not mandatory for MVP; per-tenant toggle later | Not mandatory | 2026-07-26 | Jobs | Decided (MVP) |
| J6 | Complete without photos? | Yes — photos optional | Yes | 2026-07-26 | Jobs | Decided (MVP) |
| J7 | Track travel times? | Out of scope | — | 2026-07-26 | Jobs | Deferred |
| J8 | Billable hours required? | Capture actual hours; `billable` flag deferred | Actual hours captured | 2026-07-26 | Jobs, Quotations | Decided (MVP) |

## Workshop

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| W1 | Multiple devices per ticket? | One device per ticket for MVP | One device | 2026-07-26 | Workshop | Decided (MVP) |
| W2 | Transfer device between customers? | No — device belongs to one customer | No | 2026-07-26 | Workshop, CRM | Deferred |
| W3 | Reopen completed tickets? | No — terminal | No | 2026-07-26 | Workshop | Decided (MVP) |
| W4 | Delivery requires signature? | Optional for MVP | Optional | 2026-07-26 | Workshop | Decided (MVP) |
| W5 | Estimates required before repair? | Optional | Optional | 2026-07-26 | Workshop, Quotations | Decided (MVP) |
| W6 | Diagnostic fees required? | Optional | Optional | 2026-07-26 | Workshop | Deferred |

## Inventory

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| I1 | One or multiple warehouses? | One warehouse for MVP; `warehouse_id` seam nullable | One warehouse | 2026-07-26 | Inventory | Decided (MVP) |
| I2 | Negative stock allowed? | No — block issue below zero | No (blocked) | 2026-07-26 | Inventory, Jobs, Workshop | Decided (MVP) |
| I3 | Units of measure required? | Simple `unit` field, default `pcs` | Simple unit (default pcs) | 2026-07-26 | Inventory, Quotations | Decided (MVP) |
| I4 | Serial-tracked inventory products? | Serial tracking lives in Workshop devices for MVP | Deferred in Inventory | 2026-07-26 | Inventory, Workshop | Deferred |
| I5 | Purchase orders in scope? | Out of scope | — | 2026-07-26 | Inventory, Suppliers | Deferred |
| I6 | Returns in scope? | Out of scope (ledger supports it later) | — | 2026-07-26 | Inventory | Deferred |
| I7 | VAT-inclusive prices? | No — store net (VAT-exclusive); tax applied at quotation | Net prices | 2026-07-26 | Inventory, Quotations | Decided (MVP) |

## Quotations

| # | Question | Default recommendation | Decision | Date | Impacted | Status |
|---|----------|------------------------|----------|------|----------|--------|
| Q1 | One currency per tenant or per quotation? | Per tenant (single currency) | Per tenant | 2026-07-26 | Quotations, Inventory | Decided (MVP) |
| Q2 | Discounts line / document / both? | Both — per-line + optional document-level | Both | 2026-07-26 | Quotations | Decided (MVP) |
| Q3 | Quotation versions? | No versioning — snapshot pricing on send | No versions (snapshot) | 2026-07-26 | Quotations | Decided (MVP) |
| Q4 | Customers accept electronically? | Staff sets accepted/rejected for MVP; e-accept link later | Staff-set status | 2026-07-26 | Quotations | Deferred (e-accept) |
| Q5 | Accepted quotations create jobs? | Yes — accepted quote can spawn a job | Yes | 2026-07-26 | Quotations, Jobs | Decided (MVP) |
| Q6 | Quotations expire automatically? | Yes — `valid_until`; scheduled job flags expired | Auto-expire | 2026-07-26 | Quotations | Decided (MVP) |

## Cross-cutting defaults (from earlier discussions)

| # | Question | Decision | Status |
|---|----------|----------|--------|
| X1 | External reps see attachments / prices? | No — restricted project-board view only, no prices/attachments | Decided (MVP) |
| X2 | SaaS admins inspect tenant business data? | No — central admins manage provisioning/licensing only, not business data | Decided (MVP) |
| X3 | Per-tenant tax & currency | Single currency + optional tax rate per tenant | Decided (MVP) |
| X4 | Data retention after cancellation / GDPR / backup-restore | Deferred — document before production | Deferred |
| X5 | Separate central guard vs tenant guard | Central/tenant auth separated by **database-per-tenant + host-scoped session cookies** (each subdomain gets its own cookie; sessions live in the tenant DB), not two named Laravel guards — Fortify is single-guard and the DB/context split already enforces every isolation rule. | Decided (MVP) |
| X6 | Tenant user primary key | bigint PK internally + `public_id` UUID for exposure (per database-conventions), rather than a UUID PK, for spatie/FK/session compatibility. | Decided (MVP) |
| X7 | Where do notification records live? | **In-app notifications, `email_logs`, and `notification_preferences` are per-tenant** (tenant db), so each tenant's history is isolated. The **queue** (`jobs`) stays **central** via `DB_QUEUE_CONNECTION=pgsql`; `QueueTenancyBootstrapper` re-initializes the tenant inside the worker. See `docs/notifications.md`. | Decided (MVP) |
| X8 | Notification delivery guarantees | Notifications are queued and released after DB commit; email is logged on send/fail (failed jobs keep tenant + correlation id). In-app delivery is idempotent (deterministic id); email idempotency is best-effort. SMS deferred. | Decided (MVP) |
| J-cancel | Job cancellation (recommended by client, needs approval) | **Adopted** — `canceled` status + `CancelJobAction` (reason recorded). Reversible via `ReopenJobAction` (managers). Per the standing "sensible-defaults, don't-block" directive. | Decided (MVP) |
| J-reopen | Reopen completed/canceled jobs | Allowed for managers (`jobs.cancel` permission); clears terminal timestamps and records history. | Decided (MVP) |
| J-multitech | Multiple technicians per job (`job_users`) | Deferred — single `assigned_user_id` for MVP; `job_users` table can be added when multi-tech scheduling is needed. | Deferred |
| J-images | Job photo processing | Native GD (validate content, EXIF orientation, downscale, JPEG re-encode to strip metadata, thumbnail), processed async via `ProcessJobImage`; stored privately per tenant. No external image library added. | Decided (MVP) |
| J-signature | Signature capture/storage | Captured on an HTML canvas (no external `signature_pad` dependency added — offline-safe), uploaded as a private PNG file; only metadata in `job_signatures`. Never a Base64 blob in a text column. | Decided (MVP) |
| D-pdf | PDF engine | **dompdf** (`dompdf/dompdf`, pure-PHP, no external binary/Chrome) behind a `PdfRenderer` contract so it can be swapped. Remote loading disabled; assets embedded as data URIs. | Decided (MVP) |
| D-store | Generated-document storage & download | Private `local` disk, tenant-prefixed path built server-side (never from input); download via temporary **signed + policy-checked** route. Documents recorded in `generated_documents` with checksum/size/metadata. | Decided (MVP) |
| D-access | External access to documents | External reps may download only **customer-facing** types (`quotation`, `customer_export`) for their own customer; job/workshop reports are internal-only. | Decided (MVP) |
| W-serial | Serial-number uniqueness | **Unique per tenant** (partial unique index on non-null serials of live rows), not per brand — simpler and matches "search by serial". Unavailable serials stored as `null` (+ `serial_number_unavailable` flag), never a fake "N/A". | Decided (MVP) |
| W-reassign | Serial belonging to another customer | **Blocked** at intake (never silently reassigned); the lookup surfaces a warning. Explicit reassignment is out of scope for the MVP. | Decided (MVP) |
| W-status | Workshop status set | The client's `in_progress`/`completed`/`delivered` only; diagnosing / awaiting-approval folded into `in_progress` for the MVP. | Decided (MVP) |
| W-delivery-email | Delivery customer email | Queued via the notification foundation as an on-demand email with the delivery PDF attached; recorded in `email_logs`. Skipped (no error) when the customer has no email. | Decided (MVP) |
| I-ledger | Stock representation | Append-only `stock_movements` ledger is the source of truth (balance = SUM of signed quantities); `inventory_stock_balances` is a reconcilable cache. No mutable `stock_quantity` column. | Decided (MVP) |
| I-negative | Negative stock | Governed by the `inventory.allow_negative_stock` tenant setting (default **off**). When off, outbound movements that would go below zero throw; enforced against a `FOR UPDATE`-locked balance so concurrent consumers cannot oversell. | Decided (MVP) |
| I-cost | Cost visibility | Purchase cost, supplier last-purchase price, and price history require `inventory.view_cost` (owner/admin/manager). Technicians (`inventory.view`) select parts without seeing cost. | Decided (MVP) |
| I-warehouse | Warehouses | Schema is multi-warehouse; MVP operates a single default warehouse (`MAIN`). Transfers modelled in the enum for future use. | Decided (MVP) |
| I-serial-sku | SKU uniqueness | SKU unique per tenant; serial-style uniqueness handled in the workshop module. | Decided (MVP) |
| Q-money | Quotation money representation | **Integer minor units** everywhere (`App\Support\Money`, `QuotationCalculator`); no floating-point arithmetic on totals. Client previews are advisory; the server recomputes and persists. | Decided (MVP) |
| Q-snapshot | Line snapshots | Each line keeps the item relation plus snapshots of internal name / cost / sale price / tax at quotation time; later inventory changes never alter historical quotations. | Decided (MVP) |
| Q-alias | Customer aliases vs internal identity | Customer PDF shows only the `customer_alias`; internal item name and cost are visible in-app only with `inventory.view_cost`. | Decided (MVP) |
| Q-lock | Edit locking | Only `draft` quotations are editable; `sent` and terminal quotations are locked (accept/reject/expire still allowed). | Decided (MVP) |
| Q-expire | Expiry | Manual `ExpireQuotationAction` plus a daily `quotations:expire` scheduled command across tenants. | Decided (MVP) |
| Q-convert | Accepted → job conversion | Deferred — accepted quotations do not yet spawn a job (future enhancement). | Deferred |
| C360-lazy | Customer 360 loading | Initial request loads only summary + primary contact/address + small counts; each history tab is an `Inertia::optional` prop paginated with its own page param, resolved only on partial reload. Documents use signed + policy-checked URLs. Soft-deleted rows excluded. Internal-only. | Decided (MVP) |
| DASH-perms | Dashboard role-awareness | Widgets are gated by **permission** (not hardcoded role names), so roles naturally get relevant summaries; external reps get board-only widgets; central admins get none. | Decided (MVP) |
| SEARCH-pg | Search backend | **PostgreSQL ILIKE + `pg_trgm` GIN indexes** — no external search engine until measurement justifies it. Authorization is enforced at the query level (forbidden types never queried; tasks/boards board-scoped; external reps board-only). | Decided (MVP) |
| ADM-features | Feature flags | Per-tenant `features` jsonb on the central `tenants` row, **default-on** (`hasFeature` = flag ?? true). Routes gate with the `feature:` middleware (quotations wired as the reference; others follow the same pattern). | Decided (MVP) |
| ADM-deletion | Tenant deletion | **Staged** active→suspended→archived→deletion_pending→deleted, with a retention window (`purge_after`), double confirmation (accept + retype tenant name), and a purge guard that refuses before retention elapses. Audited to `tenant_provisioning_logs`. | Decided (MVP) |
| ADM-support | Support access | Implemented as an **audited, time-limited `SupportSession`** (reason mandatory) — the record + banner satisfy "no silent impersonation". Full cross-DB login-as-tenant is out of scope for the MVP (the audit + policy scaffold is in place). | Decided (MVP) |
| ADM-audit | Tenant lifecycle audit | Tenant lifecycle events log to the central `tenant_provisioning_logs` (its `subject_id` is a string) rather than `activity_log` (bigint subject_id can't hold a UUID tenant id). | Decided (MVP) |
| SEC-backup | Backup mechanism | App-level **encrypted logical JSON** backup (`BackupTenant`/`RestoreTenant`, `tenants:backup`) that is **restore-tested** in the suite; production additionally uses `pg_dump`/managed snapshots + object-storage versioning. Restore uses `session_replication_role=replica` to defer FK checks. | Decided (MVP) |
| SEC-csp | Content-Security-Policy | Pragmatic CSP compatible with the Inertia/Vite SPA (`'unsafe-inline'`/`'unsafe-eval'` for scripts/styles) via `SecurityHeaders` middleware. Tighten with nonces/hashes if the asset pipeline gains SRI support. | Decided (MVP) |
| SEC-dsar | GDPR / DSAR automation | Deferred — data categories, retention, and responsibility split are documented (`docs/privacy.md`); a customer-facing export/erasure workflow and written DPA are pre-production items (see X4). | Deferred |
| DEP-batched | Tenant migrations at scale | `tenants:migrate-batched` runs migrations in resumable batches with per-tenant state (`tenant_migration_runs`) and `--retry-failed`, instead of a single `tenants:migrate` sweep — so one bad tenant doesn't block or re-touch the rest. | Decided (MVP) |
| DEP-expand | Schema change discipline | **Expand-and-contract** required: releases add schema (`expand`) backward-compatibly; readers of the old shape are removed before a later `contract` drops it. Enables code-only rollback (old code tolerates the new schema). | Decided (MVP) |
| DEP-image | Deployment artifact | ~~Single multi-stage Docker image runs web/worker/scheduler.~~ **Superseded — deployment is native (no Docker), per client decision.** nginx + php-fpm on the host; queue worker as a systemd service; scheduler via cron; `scripts/deploy.sh` builds + migrates + restarts. OPcache `validate_timestamps=0` (re-`config:cache` + reload php-fpm per deploy). Full runbook: `docs/ubuntu-server-setup.md`. Managed Postgres/Redis/S3 still recommended in production. | Decided (native) |
| DEP-envgate | Release config gate | `app:validate-env` is a hard gate in `scripts/deploy.sh` and CI; production requires `APP_DEBUG=false`, https `APP_URL`, persistent session/cache, non-sync queue, and the central-pinned queue connection. | Decided (MVP) |
| DEP-deploystep | Push-to-deploy automation | Native flow: `git pull` on the host then `scripts/deploy.sh` (build → central migrate → batched tenant migrate → cache → worker restart → validate/health → smoke). Fully automated push-to-host (CI/webhook) is left as a documented manual gate (staging → checklist → production) rather than wired to specific infra. | Deferred |
| API-domain | Route domain split | Central vs tenant routes isolated by `central`/`tenant` middleware (404 on the wrong domain) rather than a hard `Route::domain` lock — keeps Wayfinder URLs same-origin/relative (no CORS). Central admin routes prefixed `/admin` per convention; tested in `TenantIsolationTest`. | Decided (MVP) |
| API-transitions | Transition endpoints | Protected state changes are explicit `POST /resource/{id}/verb` endpoints (one per transition, guarded + audited); `PUT /resource/{id}` is field-edit only; no endpoint accepts a caller-supplied lifecycle status. See `docs/api-and-routes.md`. | Decided (MVP) |
| API-users-path | `/users` vs `/employees` | Kept `/users` (convention suggested `/employees`) because the module manages internal employees **and** external customer-rep users — `/users` is the accurate umbrella. Documented deviation. | Decided (MVP) |
| QS-scheduler | Scheduler fan-out | Tenant fan-out commands run via `App\Support\Tenancy\ActiveTenants` — active tenants only, each initialized via `$tenant->run()`, per-tenant failures logged and isolated (one bad tenant never stalls the rest). Schedules take `withoutOverlapping` + `onOneServer`. Payloads carry stable ids, not model graphs. See `docs/queue-and-scheduler.md`. | Decided (MVP) |
| QS-jobs | Queue job coverage | All planned queued jobs exist except **RebuildSearchIndex** (N/A — trigram ILIKE, no index to rebuild) and **ExportCustomerData** (deferred with DSAR, `SEC-dsar`; `DocumentType::CustomerExport` reserved). | Decided (MVP) |
| DB-money-repr | Money representation | Two exact representations by role (`docs/database-conventions.md`): quotation/customer-facing **totals** = integer minor units via `App\Support\Money`; inventory catalog/ledger **per-unit prices** = exact `decimal(12,2)` (numeric, not float), crossing into totals via `Money::toMinor`. Never a PHP `(float)` on money (fixed `costSnapshot`/`AdjustStockAction` to keep money as exact string). Quantities stay `decimal(14,3)`. | Decided (MVP) |
| PERM-matrix | Permission matrix | Role grants reconciled to the suggested matrix (`docs/permission-matrix.md`). Corrected two hard cells: Technician gains `tasks.view` (reach internal boards); Sales gains `inventory.view` (build quotations, cost still hidden). "Optional" cells kept at documented defaults. Locked by a hard-cell conformance test. | Decided (MVP) |
| PERM-ext-create | External create-task | Suggested matrix has "Create project task = Yes" for external reps; **client confirmed it stays denied** (keeps X1: external = restricted review-only). The one signed-off deviation from the matrix. Enable path documented in `docs/permission-matrix.md`. | Decided (kept denied) |
| SM-canonical | State machines | Canonical lifecycles documented in `docs/state-machines.md`; every transition goes through a guarded, audited action (no generic status setter). Reconciliation added `CancelQuotationAction` (draft\|sent→canceled) and a `review→todo` reject option for project tasks. | Decided (MVP) |
| SM-reopen | Administrative reopen | `ReopenTaskAction`/`ReopenJobAction` (completed/canceled → in_progress) are intentional, guarded, audited reversals **beyond** the canonical forward machine, documented as such. Workshop/quotation have no reopen (create a new record instead). | Decided (MVP) — task variant superseded by SM-dynamic-boards |
| SM-dynamic-boards | Dynamic board columns | Client requested a flexible Kanban: user-defined **board columns** (`board_columns`, per board, add/rename/reorder/delete) with free drag-and-drop + a "move to step" dropdown. **Supersedes the fixed task state machine for tasks only** (start/submit-review/approve/reject/complete/reopen actions + endpoints removed; jobs/workshop/quotation keep their guarded machines). A single guarded `MoveTaskAction` + `POST /tasks/{task}/move` moves a task to any column on its board; the task's `status`/`completed_at` are **derived from the destination column's category** (a `TaskStatus`), keeping dashboards/search/notifications coherent. Client chose **all boards free-move**: external reps move tasks on their own project boards (generalizes the retired approve/reject; moving into "done" = customer approval), column management stays internal-only. Preserves X1 (external = restricted to their project boards). History/activity/`TaskTransitioned` still recorded on column change. | Decided (MVP) |
| Q-eaccept | Electronic quotation acceptance | The customer accepts/rejects a *sent* quotation through a **public signed link** (no login) — `URL::signedRoute` + the `signed` middleware on guest tenant routes (`/quotations/{q}/review[/accept|/reject]`), alongside the invitation guest routes. Works **without a mail server**: the internal owner copies the signed link from the quotation page and sends it manually (like invites, `INV-no-mail`). The public view is customer-safe (never internal cost/name). `CustomerDecideQuotationAction` records the decision with an **actor-less** `quotation_status_history` row + `decided_by_name`/`decided_ip` capture; the internal owner is notified (`QuotationDecidedNotification`). Re-submitting on an already-decided quote is a no-op redirect. Link is non-expiring (only actionable while status = Sent). Realises the deferred **Q4**. | Decided (MVP) |
| RBAC-custom-roles | User-defined roles | A Roles admin page (`/roles`, internal, `roles.manage`) lets users **create / re-permission / delete custom roles** on top of the fixed enum roles. `App\Domain\Authorization\RoleCatalog` bridges the two: enum roles are **system** (shown but locked — protects the permission matrix + its conformance tests), everything else is **custom**. Custom roles are **internal-only** (external stays the single fixed `customer_representative`), so a bad custom role can never widen external customer reach (keeps X1). Role-assignment validation (invite / direct-create / change-role) switched from `Rule::in(Role::values())` to `RoleCatalog::assignableFor($userType)` so custom roles are assignable; deleting a role in use is blocked. Custom-role display names are humanized client-side (`roleLabel`); system roles keep their `roles.*` translations. | Decided (MVP) |
| INV-no-mail | Invitations without a mail server | `MAIL_MAILER=log` by default and a deployment may have no SMTP. Invitations never depend on email: the Team page always surfaces a **copyable accept link** (invite + "Copy link" on pending), and `sendInvitationEmail` is **best-effort** (mail failures are logged, never fatal). Opt-in tenant setting `invitations.auto_accept` (Settings → Company) creates the account immediately with a one-time **temporary password** shown to the inviter to relay. Auto-accept derives the name from the email local-part; the user should change the temp password after first sign-in. | Decided (MVP) |
| PMVP-backlog | Post-MVP enhancements | The 24 "possible later work" items are a **backlog only**, themed and dependency-mapped in `docs/post-mvp-roadmap.md`. Not in scope; each needs **explicit per-item approval** and must not be mixed into the MVP. Supersedes the scattered per-module `Deferred` rows for these features (which remain for traceability). | Deferred |

## Definition of Done — status

- [x] High-risk ambiguities documented (all client questions captured above).
- [x] MVP defaults recorded and adopted (delegated approval per standing directive).
- [x] Deferred items explicitly marked out of scope (`Deferred` rows).
- [x] No code will be generated from unresolved assumptions without a corresponding row here.
