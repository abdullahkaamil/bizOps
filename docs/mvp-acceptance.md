# MVP Acceptance & Non-Functional Conformance

Maps the MVP definition (§41) and non-functional criteria (§42) to the
implementation. ✅ done · ⚠️ partial / gap · ⛔ not done.

## MVP definition (§41)

### Platform
| Item | Status | Evidence |
|------|--------|----------|
| Central admin creates/suspends tenants | ✅ | `Admin/TenantController`, `TenantProvisioner`, suspend/reactivate |
| Separate database per tenant | ✅ | stancl multi-db; `TenantIsolationTest` |
| Subdomain initializes correct context | ✅ | `InitializeTenancyBySubdomain` (global); isolation tests |
| Tenant users authenticate locally | ✅ | Fortify, host-scoped sessions in tenant DB |
| Roles & policies enforced | ✅ | spatie + per-model policies; `TenantRbacTest` |
| Files tenant-isolated | ✅ | tenant-prefixed private disks; signed URLs; `SecurityTest` |

### CRM
| Item | Status | Evidence |
|------|--------|----------|
| Manage customers/contacts/addresses | ✅ | `app/Domain/CRM`, `CustomerPolicy` |
| Lazy-loaded history tabs | ✅ | `CustomerController@show` `Inertia::optional`, paginated per tab |

### Tasks
| Item | Status | Evidence |
|------|--------|----------|
| Internal & project boards | ✅ | `BoardType`, `BoardPolicy` |
| External reps see only assigned boards | ✅ | `BoardPolicy`/`TaskPolicy`; `TaskBoardTest` |
| Customer review & rejection | ✅ | Approve/Reject actions (reject → todo or in_progress) |
| Activity & status history retained | ✅ | `task_status_history` (immutable) + activity log |
| Due reminders | ✅ | `tasks:notify-due-soon` (active-tenant, locked) |
| **Kanban drag-and-drop + rollback** | ⚠️ | Boards work via **explicit action buttons**; no HTML5/lib drag-and-drop yet (Milestone 10 item) |

### Jobs
| Item | Status | Evidence |
|------|--------|----------|
| Managers create/assign | ✅ | `CreateJobAction`/`AssignJobAction`, `JobPolicy` |
| Technicians start from mobile | ✅ | mobile `jobs/*` UI, `StartJobAction` (server time) |
| Notes, photos, signatures stored | ✅ | GD image pipeline (files), PNG signatures (files) |
| Completion uses server timestamps | ✅ | `CompleteJobAction` server `now()` |
| PDF service report | ✅ | `GenerateDocument` + `JobServiceReportGenerator` |

### Workshop
| Item | Status | Evidence |
|------|--------|----------|
| Devices registered; serial lookup + repair history | ✅ | `customer_devices` (partial-unique serial) |
| Tickets link to jobs | ✅ | `LinkWorkshopTicketToJobAction` |
| Linked job closure validation | ✅ | `CompleteJobAction::assertWorkshopConstraints` (open ticket blocks completion) |
| Completion & delivery | ✅ | Complete/Deliver actions |
| Delivery PDF emailed | ✅ | `WorkshopDeliveredNotification::toMail` attaches the generated `WorkshopDeliveryReport` |

### Inventory
| Item | Status | Evidence |
|------|--------|----------|
| Items & suppliers | ✅ | `app/Domain/Inventory` |
| Stock ledger | ✅ | append-only `stock_movements`, `StockLedger` (locked balance) |
| Part consumption reduces stock | ✅ | `AddPartToWorkshopTicketAction` (transactional) |
| Cost visibility permission-controlled | ✅ | `inventory.view_cost` / `viewCost` policy |

### Quotations
| Item | Status | Evidence |
|------|--------|----------|
| Create quotations | ✅ | `CreateQuotationAction` |
| Inventory lines retain internal identity | ✅ | `internal_name_snapshot` + `inventory_item_id` |
| Customer aliases on PDF | ✅ | `customer_alias`; `QuotationGenerator` |
| Server-side totals | ✅ | `QuotationCalculator` (integer minor units) |
| PDFs generated & stored | ✅ | `GenerateDocument` + `QuotationGenerator` |

### Operations
| Item | Status | Evidence |
|------|--------|----------|
| Role-relevant dashboard | ✅ | `DashboardData` |
| Global search | ✅ | `SearchService` (query-level authz) |
| Queue & scheduler tenant-aware | ✅ | central-pinned queue; `ActiveTenants`; `QueueSchedulerTest` |
| Backups & restore tested | ✅ | `BackupTenant`/`RestoreTenant`; `BackupTest` |
| Monitoring & audit logs | ✅ | request-id context, auth/role/tenant audit, `/up`, `app:health-check` |

## Non-functional (§42)

| Criterion | Status | Notes |
|-----------|--------|-------|
| Paginated lists / **no unbounded list endpoints** | ⚠️ | Customer list + all Customer-360 tabs paginate; **top-level Jobs / Quotations / Inventory / Workshop / Suppliers / Users / Boards index endpoints use `->get()` without pagination** — bounded in practice for small tenants but should be paginated |
| No major N+1 | ✅ | eager `with()` on list/detail queries |
| Images processed asynchronously | ✅ | `ProcessJobImage` |
| PDFs queued if slow | ✅ | `GenerateDocument` queueable |
| Health / queue / DB / storage monitoring | ✅ | `/up`, `app:health-check` (db/cache/storage/queue) |
| Graceful failed-email handling | ✅ | `TenantMailChannel` logs on failure, preserves tenant + correlation |
| Data integrity (txns, immutable history, FKs, unique sequences, ledger, server timestamps, quotation snapshots) | ✅ | across modules |
| Accessibility (keyboard, focus, labels, non-color status, touch targets) | ⚠️ | shadcn-vue primitives + `StatusBadge` (label + color); **no automated a11y/interaction test harness** (vitest/playwright deferred) |
| Browser support matrix | ⛔ | not formally defined (recommend current Chrome/Edge/Firefox/Safari + mobile) |

## Process (§36, §43, §45)

| Item | Status | Notes |
|------|--------|-------|
| One-milestone-at-a-time, inspect-before-edit, tests-with-impl, format+test, docs, report assumptions | ✅ | followed throughout |
| Don't build future phases / replace working architecture / broad dep upgrades | ✅ | honored |
| **Branch strategy** (main/develop/feature/*) | ⛔ | all work is **uncommitted on `main`** — not yet split into per-phase branches |
| Staging deploy → acceptance → production (build order 23–25) | ⛔ | operational; native deploy tooling ready (`scripts/deploy.sh`, `docs/ubuntu-server-setup.md`) but not executed |

## Open gaps summary

1. **Unbounded top-level list endpoints** — paginate Jobs/Quotations/Inventory/Workshop/Suppliers/Users/Boards indexes (§42).
2. **Kanban drag-and-drop + rollback-on-failure** — boards work via action buttons; DnD (Milestone 10) not built (needs a drag library).
3. **Automated accessibility/interaction tests** — no vitest/playwright harness (deferred since Phase 8).
4. **Branch organization** — split the uncommitted `main` tree into per-phase branches/commits.
5. **Browser support matrix** — define before launch.
