# Queue & Scheduler

How background work runs in a multi-tenant, database-per-tenant deployment.

## Tenant-aware queue

The `jobs` table is **pinned to the central connection** (`DB_QUEUE_CONNECTION=pgsql`,
enforced by `app:validate-env`). A worker boots the framework once and processes
jobs for many tenants; stancl's `QueueTenancyBootstrapper` re-initializes the
correct tenant for each job from the tenant id serialized onto the payload, then
tears it down afterwards. This is why the queue connection must **never** be
switched to a tenant database — the jobs table lives centrally and the tenant is
swapped *inside* the worker per job.

**Payloads carry stable identifiers, not model graphs.** Every job/notification
stores scalar ids and re-fetches inside `handle()`, so a job that sits on the
queue can never act on a stale serialized model:

- `GenerateDocument(type, relatedType, relatedId, actorId)` — re-`find()`s the model.
- `ProcessJobImage(jobImagePublicId)` — resolves by public id.
- Notifications (`TaskDueSoonNotification`, …) take ids + a correlation id.

## Queued jobs

| Plan job | Implementation |
|----------|----------------|
| GenerateJobPdf | `GenerateDocument` + `JobServiceReportGenerator` |
| GenerateWorkshopPdf | `GenerateDocument` + `WorkshopCompletionReportGenerator` / `WorkshopDeliveryReportGenerator` |
| GenerateQuotationPdf | `GenerateDocument` + `QuotationGenerator` |
| SendWorkshopDeliveryEmail | `WorkshopDeliveredNotification` |
| SendTaskReminderEmail | `TaskDueSoonNotification` |
| SendTaskReviewNotification | `TaskSubmittedForReviewNotification` |
| SendTaskRejectedNotification | `TaskRejectedNotification` |
| ProcessUploadedImage | `ProcessJobImage` (GD: validate, re-encode, strip metadata, thumbnail) |
| RebuildSearchIndex | **N/A** — search is PostgreSQL ILIKE + `pg_trgm` GIN indexes; there is no separate index to rebuild |
| ExportCustomerData | **Deferred** (DSAR/GDPR, register `SEC-dsar`); `DocumentType::CustomerExport` is reserved |

Notifications are queued after the DB commit (`afterCommit`), select channels from
per-tenant preferences, and write `email_logs` on send/failure. In-app delivery is
**idempotent** via a deterministic id derived from the correlation id.

## Central scheduler

Fan-out commands follow one safe pattern via `App\Support\Tenancy\ActiveTenants`:

```
For each ACTIVE tenant (cursor):
  → $tenant->run(): initialize the tenant safely
  → do the work (find due reminders / expiring quotations / …)
  → on failure: log with tenant id and CONTINUE (isolate the failure)
Return {processed, failed}
```

`ActiveTenants::each()` guarantees the plan's requirements:

- **Only active tenants** are visited — suspended/archived/provisioning/deleted are
  skipped (a suspended tenant must not receive reminders).
- **Each tenant initializes safely** through `$tenant->run()`.
- **A failing tenant is logged and does not stop the others.**

Scheduled commands (`routes/console.php`), each with an overlap lock:

| Command | Frequency | Lock |
|---------|-----------|------|
| `tasks:notify-due-soon` | hourly | `withoutOverlapping` + `onOneServer` |
| `quotations:expire` | daily 02:00 | `withoutOverlapping` + `onOneServer` |
| `tenants:backup` | daily 01:00 | `withoutOverlapping` + `onOneServer` |

`withoutOverlapping` prevents a slow run (many tenants) from being started again
concurrently — no duplicate reminders. `onOneServer` ensures a single run across a
horizontally-scaled scheduler fleet.

### Scale

Tenants are streamed with a cursor (constant memory). For larger fleets, batch by
tenant id range across multiple scheduler shards (each shard runs the same command
with a `--shard`/range filter) — the overlap lock keeps each shard's runs serial.

## Tests

`tests/Tenancy/QueueSchedulerTest.php` and `tests/Feature/ScheduleLockTest.php`:

1. **Correct tenant initializes** — the reminder command enters the tenant and
   notifies its assignees.
2. **Suspended tenant handled per policy** — a suspended tenant is skipped (no
   notification).
3. **Retried / overlapping run is safe** — running twice does not double-notify
   (deterministic in-app id).
4. **A failing tenant does not stop others** — `ActiveTenants` isolates the
   failure (`{processed:1, failed:1}`) and the healthy tenant's work still lands.
5. **Scheduler lock** — the fan-out schedules are registered `withoutOverlapping`
   and `onOneServer`.
