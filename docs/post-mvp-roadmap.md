# Post-MVP Roadmap (Phase 23)

> **Status: backlog only.** None of the items below are in scope. Each requires
> **explicit, per-item approval** before any code is written — they must not be
> mixed into the MVP. This document exists so that when an item is approved, its
> shape, dependencies, and existing foundations are already understood.

The MVP (Phases 0–22) is complete: 226 tests, all quality gates green. The items
here are the "possible later work" enumerated in the roadmap, organized into
themes with the groundwork the current build already provides and the main
dependencies each carries.

Legend for **Foundation**: ● substantial groundwork exists · ◐ partial · ○ greenfield.

---

## 1. Scaling & topology

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Multiple warehouses | ◐ | `warehouses` table + warehouse-scoped `stock_movements` already exist (Phase 16). Remaining: warehouse CRUD UI, per-warehouse balances in the ledger UI, and stock-transfer movements between warehouses. |
| Multiple branches | ○ | An org unit *above* departments within a tenant. Needs a `branches` table and a branch dimension on users/jobs/customers; touches RBAC scoping and most list queries. Large cross-cutting change — do after warehouses. |
| Dedicated tenant database servers | ◐ | stancl already resolves each tenant's connection; `DatabaseConfig` names the db. Remaining: per-tenant connection host/credentials + a placement strategy (shard map) and migration/backup awareness of multiple servers. |
| Data warehouse | ○ | Downstream analytics store. Best fed by the webhooks/events work below (CDC or event stream) rather than querying tenant dbs directly. Depends on **Webhooks/Public API** and **Advanced analytics**. |

## 2. Identity & access

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Central identity & tenant switching | ◐ | Today auth is tenant-local (host-scoped sessions, X5) and support access is an audited `SupportSession` (ADM-support). A central identity that can switch tenants is a significant model change — a central user directory + a cross-tenant session/token bridge. Highest-risk item; design carefully against the isolation guarantees. |
| Single sign-on (SSO) | ○ | Register row **T5** (deferred). SAML/OIDC per tenant. Fortify is single-guard; would add a socialite/SAML broker and per-tenant IdP config. |
| SCIM provisioning | ○ | Automated user lifecycle from an external IdP. Depends on **SSO** and the **Public API** (SCIM is an API surface). |
| Custom domains | ◐ | Register row **T3** (deferred). stancl supports domain identification; `domains` table exists. Remaining: per-tenant custom-domain records, TLS cert issuance/renewal (ACME), and DNS verification UX. |

## 3. Notifications & channels

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| SMS / WhatsApp notifications | ● | Register rows **X8**. The Phase 12 foundation (`TenantNotification`, preference-driven channels, `email_logs`) is channel-agnostic — add an `SmsChannel`/`WhatsAppChannel` + provider config + a `sms_logs` mirror of `email_logs`, and extend `notification_preferences`. Smallest well-scoped item here. |

## 4. Field service & mobile

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Recurring jobs | ● | `service_jobs` + status machine exist. Add a `job_schedules` table (RRULE/cron) + a scheduler command that spawns jobs, mirroring `tasks:notify-due-soon`. |
| Native mobile application | ◐ | The current mobile web UI (Phase 13) and Inertia endpoints exist. A native app most likely consumes the **Public API** (below) — build that first. |
| Offline field-service mode | ○ | Client-side queue + conflict resolution for job updates/photos/signatures. Depends on the **Public API** and idempotent write endpoints (the notification idempotency pattern is a model). |

## 5. Procurement & finance

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Purchase orders | ◐ | Register row **I5** (deferred). `suppliers` + `inventory_items` + the stock ledger exist. Add `purchase_orders`/`purchase_order_lines`; receiving posts stock-in movements. |
| Supplier invoices | ◐ | Follows purchase orders (match invoice → PO → receipt). Integer-minor money via `App\Support\Money`. |
| Time tracking & billing | ◐ | Jobs already capture actual hours (register **J8**); a `billable` flag was deferred. Add time entries + billing rates + an invoice document (reuse the Documents PDF pipeline). |
| Accounting integrations | ○ | Xero/QuickBooks sync. Depends on **Webhooks/Public API** and the finance data above. |

## 6. Customer-facing

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Electronic quotation acceptance | ● | Register row **Q4** (deferred, "e-accept link later"). Quotation model + statuses + snapshot totals + signed-URL infra all exist. Add a signed public accept/reject route + acceptance capture (IP/timestamp/signature). Pairs naturally with **Q-convert** (accepted → job). |
| Customer portal beyond project tasks | ◐ | External-rep isolation (`user_type`, board-scoped access, X1) is enforced. Extend the external layout with read-only jobs/quotations/documents views — always query-level authorized, never prices/attachments beyond policy. |

## 7. Workflow & productivity

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Recurring tasks | ● | Register row **K8** (deferred). Same pattern as recurring jobs — a schedule table + spawner over the existing `tasks` model. |
| SLA tracking | ◐ | Every module has immutable `*_status_history` with timestamps. Add SLA policies (target durations per status/priority) + breach detection over that history + alerts via notifications. |
| Workflow customization | ○ | Per-tenant configurable statuses/transitions. Large — the status machines are currently code-defined enums + action classes; making them data-driven is a deep change. Low priority. |
| Custom PDF template editor | ◐ | Documents infra (Blade templates → `DompdfRenderer`, Phase 14) exists. An editor makes templates tenant-data-driven (stored template + safe token substitution) rather than code. Mind PDF-injection safety. |

## 8. Analytics

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Advanced analytics | ◐ | `DashboardData` (Phase 19) provides role-aware widgets. "Advanced" means trend/cohort/cross-module reporting — likely wants the **Data warehouse** or at least materialized rollups to avoid heavy tenant-db queries. |

## 9. Integration & extensibility

| Item | Foundation | Notes / dependencies |
|------|-----------|----------------------|
| Webhooks | ● | Domain events already fire after-commit (e.g. `JobCompleted`, `TaskTransitioned`) and activity is logged. Add webhook subscriptions + a signed, retrying delivery job (central-pinned queue). Foundational for **accounting integrations** and the **data warehouse**. |
| Public API | ◐ | Controllers/actions/policies are already thin and reusable. Add token auth (Sanctum), versioned API routes, rate limiting, and resource transformers. Unlocks **native mobile**, **offline mode**, **SCIM**, **accounting**. Highest leverage integration item. |

---

## Suggested sequencing (when approvals come)

These are dependency-ordered suggestions, not commitments:

1. **Quick, self-contained wins:** SMS/WhatsApp channel · electronic quote acceptance · recurring jobs/tasks · finish multiple warehouses.
2. **Integration backbone:** Public API → Webhooks. Unlocks a large downstream set (mobile, offline, SCIM, accounting, data warehouse).
3. **Finance track:** purchase orders → supplier invoices → time tracking & billing → accounting integrations.
4. **Identity track:** SSO → SCIM; central identity & tenant switching (treat as its own project — highest isolation risk).
5. **Platform depth:** custom domains · SLA tracking · custom PDF editor · advanced analytics/data warehouse · dedicated db servers · multiple branches · workflow customization.

Each item, when approved, follows the same one-phase-per-branch discipline as the
MVP: migrations → backend → authorization → tests → quality gates → docs, with a
decision-register row for any new default.
