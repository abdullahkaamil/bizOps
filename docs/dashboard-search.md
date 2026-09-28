# Dashboard & Global Search

Operational visibility and fast record lookup (Phase 19).

## Role-aware dashboard
`App\Domain\Dashboard\DashboardData::for($user)` returns `{ scope, widgets }`:
- **Central** admins (no tenant context) → no operational widgets.
- **External** representatives → only `assigned_boards`, `tasks_awaiting_approval`,
  `recent_task_updates` (scoped to their own project boards). No internal data.
- **Internal** users → each widget is included **only if the viewer holds the
  governing permission**, so the dashboard is inherently role-aware:
  - `my_jobs` (`jobs.view`, assigned to me), `jobs_today` / `jobs_in_progress`
    (`jobs.assign`), `recently_completed`.
  - `my_tasks` / `tasks_due_soon` (`tasks.view`), `review_queue`
    (`tasks.submit_review`, boards I belong to).
  - `active_workshop` (`workshop.view`), `workshop_waiting_delivery`
    (`workshop.deliver`).
  - `low_stock` (`inventory.manage`), `draft_quotations` (`quotations.view`),
    `recent_activity` (`activity.view`).

  A manager (all permissions) sees everything; a technician sees jobs/workshop; a
  developer sees tasks/review; sales sees tasks/quotations.

## Global search
`App\Domain\Search\SearchService::search($user, $query)` returns groups of results
across customers, contacts, jobs (+ numbers), devices (+ serials), workshop tickets,
tasks, boards, quotations, and inventory (SKU/name).

- **Tenant-scoped** — all queries run in the tenant database.
- **Authorized at the query level** — a result type is searched **only when the
  user may see it** (permission check), and tasks/boards are pre-scoped to the
  boards the user can reach. Forbidden records are never fetched, then hidden.
  External reps can only search their own project boards + tasks.
- **PostgreSQL first** — indexed `ILIKE '%term%'` backed by **`pg_trgm` GIN
  indexes** on the high-value columns (numbers, serials, names). An external search
  engine is intentionally deferred until measurement shows the need.

### Surfaces
- Header search box (in the sidebar) → `/search?q=` results page.
- JSON endpoint `/search/results?q=` for a future command-palette.
