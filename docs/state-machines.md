# State-Machine Reference

The canonical lifecycle for each stateful entity. **Every transition is executed
through an explicit backend action** — there is no generic "set status" endpoint
that accepts an arbitrary status. Each action applies the change atomically while
appending an immutable `*_status_history` row and an activity-log entry.

Jobs, workshop tickets and quotations use fixed forward-only state machines
(below). **Tasks** are the exception: they use free movement across user-defined
board columns (see the next section) — still one guarded action, but the
destination is any column on the task's board rather than a hard-coded next state.

Status values live in PHP enums: `TaskStatus`, `JobStatus`, `WorkshopStatus`,
`QuotationStatus`.

---

## Tasks — dynamic board columns (free movement)

> **Note:** Tasks no longer use a fixed forward-only state machine. Every board
> has user-defined **columns** (`board_columns`, ordered by `position`) that
> internal staff can add / rename / reorder / delete. A task can be moved to *any*
> column on its own board via a single guarded action — drag-and-drop on the
> Kanban or the "move to step" dropdown in the task drawer.

Each column carries a **category** (a `TaskStatus` value: `todo` / `in_progress`
/ `review` / `completed`). Moving a task derives its `status` from the destination
column's category, so dashboards, search and notifications stay coherent no matter
how the columns are labelled. Moving into a `completed`-category column stamps
`completed_at`; moving out clears it.

Defaults seeded on board creation (`BoardColumn::seedDefaults`):
- Internal board: **Yapılacak** (todo), **Devam Ediyor** (in_progress), **Tamamlandı** (completed).
- Project board: the same, plus **İncelemede** (review) before "done".

| Move | Action | Guard |
|------|--------|-------|
| task → any column on its board | `MoveTaskAction` | destination column belongs to the same board; `completed_at` synced from the column category |

Authorization is two-layered:

1. **Board-level** (`TaskPolicy@move`): internal staff move freely on any board
   they can reach (`UpdateTasks`); external representatives move tasks only on
   their own project boards (`ApproveTasks`).
2. **Per-column authority** (`MoveTaskAction`): each column carries `move_in` and
   `move_out`, each one of `internal` / `external` / `both` (`ColumnAccess`,
   default `both`). To move a card the actor's user type must be allowed to pull
   it *out of* its current column **and** move it *into* the destination. A
   violation throws `AuthorizationException` (403) — so a "waiting on customer"
   step can be pulled out of only by the customer, and a "done" step restricted to
   `move_in = external` cannot be entered by internal staff. The frontend gates
   drag-and-drop with the same rule (vuedraggable `pull`/`put`); the server is the
   backstop. A pure reorder within one column is exempt from the check.

A column change appends an immutable `task_status_history` row + activity-log
entry and dispatches `TaskTransitioned` (so review/approval notifications still
fire); a pure reorder within one column does not. Column management (add / rename
/ reorder / delete / set authority) is internal-only (`BoardPolicy@manageColumns`).

The task's **definition (title + description) is immutable** once created — the
update endpoint only edits workflow metadata (priority, due date, assignees).
Later changes of intent go through comments, not edits.

## Job

```
pending
├── in_progress
│   ├── completed
│   └── canceled
└── canceled
```

| From → To | Action | Guard |
|-----------|--------|-------|
| pending → in_progress | `StartJobAction` | status = pending |
| in_progress → completed | `CompleteJobAction` | status = in_progress (+ completion requirements) |
| in_progress → canceled | `CancelJobAction` | status ∈ {pending, in_progress} |
| pending → canceled | `CancelJobAction` | status ∈ {pending, in_progress} |

Job creation records the initial `pending` status via `CreateJobAction`.

## Workshop ticket

```
in_progress
└── completed
    └── delivered
```

| From → To | Action | Guard |
|-----------|--------|-------|
| (created) → in_progress | `CreateWorkshopTicketAction` | initial |
| in_progress → completed | `CompleteWorkshopTicketAction` | status = in_progress |
| completed → delivered | `DeliverWorkshopTicketAction` | status = completed |

## Quotation

```
draft
├── sent
│   ├── accepted
│   ├── rejected
│   ├── expired
│   └── canceled
└── canceled
```

| From → To | Action | Guard |
|-----------|--------|-------|
| (created) → draft | `CreateQuotationAction` / `DuplicateQuotationAction` | initial |
| draft → sent | `SendQuotationAction` | status = draft |
| sent → accepted | `AcceptQuotationAction` | status = sent |
| sent → rejected | `RejectQuotationAction` | status = sent, reason required |
| sent → expired | `ExpireQuotationAction` | status = sent (scheduled `quotations:expire`) |
| sent → canceled | `CancelQuotationAction` | status ∈ {draft, sent} |
| draft → canceled | `CancelQuotationAction` | status ∈ {draft, sent} |

Editing (`UpdateQuotationAction`) is allowed only while `draft`
(`QuotationStatus::isEditable()`); it recomputes the snapshot.

---

## Administrative transitions (beyond the canonical forward machine)

These are **not** part of the forward lifecycle above but exist as deliberate,
guarded, audited reversals for correcting mistakes. They are called out
explicitly so the divergence from the diagrams is intentional and visible, not
hidden drift. Each still goes through an action and records history.

| Entity | Transition | Action | Guard |
|--------|-----------|--------|-------|
| Task | completed → in_progress (reopen) | `ReopenTaskAction` | status = completed |
| Job | completed → in_progress (reopen) | `ReopenJobAction` | status ∈ {completed, canceled} |
| Job | canceled → in_progress (reopen) | `ReopenJobAction` | status ∈ {completed, canceled} |

Workshop tickets and quotations have **no** reopen path — once delivered or in a
terminal quotation state, a new ticket/quotation is created instead.

## Invariants

- No transition bypasses its action; controllers call the action, which guards
  state before mutating. Illegal transitions throw `InvalidXTransition`.
- Every transition writes a `*_status_history` row (`from_status`, `to_status`,
  actor, reason, timestamp) and an `activity_log` entry.
- Terminal states per the diagrams: task `completed`; job `completed`/`canceled`;
  quotation `accepted`/`rejected`/`expired`/`canceled`; workshop `delivered`.
  Only the administrative reopen transitions above re-open a terminal state.
