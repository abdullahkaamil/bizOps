# Permission Matrix

The default role→permission grants seeded into every tenant, reconciled against
the suggested initial matrix. Source of truth: `App\Enums\Role::permissions()`,
seeded idempotently by `TenantDatabaseSeeder` (re-run `php artisan tenants:seed`
to re-sync existing tenants).

Roles: **Owner/Administrator** (all permissions), **Manager**, **Developer**,
**Technician**, **Sales**, **External** (`customer_representative`).

`Yes` = granted · `No` = denied · `Opt` = client discretion (our chosen default
shown in parentheses).

| Capability | Owner/Admin | Manager | Developer | Technician | Sales | External |
|------------|:-----------:|:-------:|:---------:|:----------:|:-----:|:--------:|
| Customers view | Yes | Yes | Opt (yes) | Opt (yes) | Yes | No |
| Customers manage | Yes | Yes | No | No | Yes | No |
| Internal boards (reach) | Yes | Yes | Yes | **Yes** | Opt (yes) | No |
| Project boards assigned | Yes | Yes | Yes | Opt (no) | Opt (no) | Yes |
| Create project task | Yes | Yes | Yes | Opt (no) | Opt (no) | **No³** |
| Submit task for review | Yes | Yes | Yes | Opt (no) | No | No |
| Approve review task | Opt (no)¹ | Opt (no)¹ | No | No | No | Yes |
| Jobs view | Yes | Yes | Opt (yes) | Yes | Opt (no) | No |
| Jobs create/assign | Yes | Yes | No | Opt (no) | Opt (no) | No |
| Jobs start/complete | Yes | Yes | Opt (no) | Yes | No | No |
| Workshop view | Yes | Yes | Opt (no) | Yes | Opt (no) | No |
| Workshop complete | Yes | Yes | No | Yes | No | No |
| Inventory view | Yes | Yes | Opt (no) | Yes | **Yes** | No |
| Inventory cost | Yes | Yes | No | Opt (no) | Opt (no) | No |
| Quotations create | Yes | Yes | No | No | Yes | No |
| Settings manage | Yes | Opt (no)² | No | No | No | No |

¹ **Approve review task** for internal Owner/Manager is `Optional`; our default is
**no** — approving a customer review is the customer's decision, enforced in
`TaskPolicy::approve` (external representative only), even though Owner/Admin hold
the `tasks.approve` permission. This matches the workflow rule "internal cannot
approve customer reviews by default".

² **Settings manage** = `settings.update`. Manager holds `settings.view` (read the
company settings) but not `settings.update` by default (`Optional` → no).

## Board/task cells are permission + policy

The task rows are not single permissions — reach and actions are enforced by
`BoardPolicy`/`TaskPolicy` combining a permission, board **type**
(internal/project), and board **membership**:

- **Internal boards (reach)** = `tasks.view` (internal users see any board;
  members can be assigned). Technician now holds `tasks.view` to satisfy the
  matrix; task **write** actions (start/update/complete) for technicians remain
  `Optional` and are not granted by default.
- **Project boards assigned** = external reps reach only project boards they are a
  member of (`Board::hasMember`); internal reach via `tasks.view`.
- **Approve/Reject review** = external member of the project board only.

## Reconciliation changes made

Against the previously-seeded defaults, two hard cells were corrected:

1. **Technician → `tasks.view`** ("Internal boards" = Yes). Lets technicians reach
   the internal boards they are assigned to.
2. **Sales → `inventory.view`** ("Inventory view" = Yes). Lets sales see
   stock/products when building quotations; cost stays hidden (`inventory.view_cost`
   not granted).

Locked by the test *"role permissions conform to the suggested permission matrix
(hard cells)"* in `tests/Tenancy/TenantRbacTest.php`.

³ **External "Create project task"** — the suggested matrix marks this Yes, but the
client **confirmed it stays denied** (keeping decision **X1**: external reps get a
restricted project-board view with review actions only). `TaskPolicy::create` /
`BoardPolicy::create` remain internal-only and `customer_representative` does not
hold `tasks.create`. This is the one deliberate, signed-off deviation from the
suggested matrix. If it is ever revisited, the enable path is: grant `tasks.create`
to `customer_representative`, allow external members on their own project board in
`BoardPolicy`/`TaskPolicy::create`, and keep every other external restriction (no
prices, no internal comments/attachments, no submit-for-review) intact.
