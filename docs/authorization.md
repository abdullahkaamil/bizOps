# Authorization

All authorization is enforced **server-side** through Laravel policies/gates. The frontend only mirrors permissions to hide/disable controls — never as security.

## User types
- **Central administrator** — SaaS operator on `dashboard.kaamil.test`; manages tenant provisioning + licensing only, not tenant business data.
- **Internal user** — a tenant employee. Roles + permissions via `spatie/laravel-permission` inside the tenant DB.
- **External customer representative** — a restricted user inside a tenant with access limited to assigned project boards; **no** prices, attachments, or internal comments. (Planned module.)

## Roles (tenant-local, seeded per tenant)
`owner`, `administrator`, `manager`, `developer`, `technician`, `sales`, `customer_representative` (see `App\Enums\Role`). Seeded into every tenant DB by `TenantDatabaseSeeder`. `owner`/`administrator` hold every permission; the rest are scoped to their function. `customer_representative` is the only **external** role (`Role::userType()`); its user gets `user_type = external`. Department-based scoping is planned.

## Permissions
Defined in `App\Enums\Permission`, named `resource.action` — users, roles, activity, customers, tasks, jobs, workshop, inventory, quotations, settings, reports (e.g. `users.create`, `tasks.approve`, `jobs.complete`, `inventory.view_cost`). Roles map to permission sets in `Role::permissions()`.

## External-user restrictions
Enforced at three levels: (1) the **`internal` route middleware** (`EnsureInternalUser`) 403s external users from internal areas; (2) **policies** deny abilities external users lack; (3) **query scoping** (per module) limits external users to their assigned boards, excluding prices/attachments/internal comments. External users may only reach their profile, assigned project boards + tasks, customer-visible comments/attachments, review approve/reject, and logout.

## Vue authorization
`useAuthorization()` exposes `can`, `canAny`, `canAll`, `hasRole`, `isExternal`, `isInternal`, `isCentralAdmin` — presentation only. Backend policies remain authoritative.

## Per-model policies
`UserPolicy`, `TenantSettingPolicy`, `CustomerPolicy`, `BoardPolicy`, and `TaskPolicy` exist. `JobPolicy`, `WorkshopTicketPolicy`, `InventoryItemPolicy`, `SupplierPolicy`, `QuotationPolicy`, and `DocumentPolicy` are created **with their models** in the respective module phases (permission-gated, state-aware). Policies for models under `app/Domain` are registered explicitly in `AppServiceProvider` (e.g. `Gate::policy(Customer::class, CustomerPolicy::class)`).

## Board membership
Boards have explicit membership (`board_users`). Board actions live **outside** the `internal` route middleware so external reps can reach them; isolation is enforced purely by `BoardPolicy`/`TaskPolicy`:
- **External reps**: reachable only on project boards where they are a member; internal boards and non-member project boards 403. They may create customer-visible comments/attachments and approve/reject review tasks — nothing else.
- **Internal staff**: gated by `tasks.*` permissions; mutating a task requires board membership. Task creators and assignees are auto-added as board members.
- **Approval default-deny**: `TaskPolicy::approve`/`reject` require `isExternal()` — internal users cannot approve a customer review even with `tasks.approve`.
See `docs/workflows.md` → *Task* for the full transition/actor matrix.

## State-dependent authorization
Some abilities depend on record state (e.g. only an `in_progress` job can be finished; only a `draft` quotation can be edited; completed records cannot be reopened). Policies receive the model and check both permission **and** current status/enum. State transitions run through action classes, never raw status writes from the client.

## Policy conventions
- One policy per model in `app/Domain/<Module>/Policies` (or `app/Policies` for shared models), auto-discovered by Laravel naming.
- Methods: `viewAny, view, create, update, delete` plus domain abilities (`manageRoles`, transition-specific abilities).
- Controllers call `$this->authorize(...)`; Form Requests may also authorize.
- Self-protective rules live in the policy (e.g. a user cannot delete their own account from the team screen).
- Example: `App\Policies\UserPolicy` maps abilities to `Permission` enum values via `$user->can(...)`.
