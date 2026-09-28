# Notifications

Reusable, tenant-aware notification foundation (Phase 12). Later modules dispatch
events through it and never touch mail plumbing themselves.

## Channels
- **In-app** — Laravel database notifications, table lives **per tenant**.
- **Email** — via `TenantMailChannel`, every send audited in `email_logs`.
- **SMS** — reserved for later; add a channel + preference column when needed.

## Working without a mail server (invitations)

`MAIL_MAILER` defaults to `log`, and a deployment may have no SMTP at all. User
invitations therefore never depend on email delivery:

- **Copyable link (default).** Inviting a user (or "Copy link" on a pending
  invite) always returns the accept link in the Team page UI to relay manually.
  The email is still attempted, but **best-effort** — `UserController@sendInvitationEmail`
  swallows and logs any mail failure so the invite never 500s.
- **Auto-accept (opt-in).** The tenant setting `invitations.auto_accept`
  (Settings → Company) makes an invite create the account immediately with a
  one-time **temporary password** shown to the inviter to hand over — no link
  round-trip. See register `INV-no-mail`.

## Types (`App\Domain\Notifications\Enums\NotificationType`)
`user_invited`, `task_assigned`, `task_due_soon`, `task_created`, `task_commented`,
`task_moved`, `task_action_needed`, `job_assigned`, `workshop_completed`,
`workshop_delivered`, `quotation_sent`, `quotation_decided` (customer
accepted/rejected via the public link → notifies the quote's internal owner). Task + invite types are wired now; the
Job/Workshop/Quotation types are enum-ready for their module phases.

## Board communication engine

The boards module is communication-first: every board member (internal **and**
external) is kept in the loop, with a stronger alert for whoever must act next.

- **New task** (`TaskController@store`) → `TaskCreatedNotification` to every active
  board member except the creator.
- **New comment** (`TaskCommentController@store`) → `TaskCommentedNotification`,
  **visibility-scoped**: an `internal` comment reaches internal members only, a
  `customer` comment reaches everyone. External reps never receive internal notes.
- **Any move** (`SendTaskTransitionNotifications` on `TaskTransitioned`, forward or
  backward) → `TaskMovedNotification` to all board members except the actor —
  **except** the party that owns the destination step, who instead gets the
  targeted `TaskActionNeededNotification` ("waiting on you"). Ownership = whoever
  may move a card *out* of that column (`ColumnAccess move_out`); `both` means no
  single owner, so everyone just gets the broad move notice. This is how the
  customer gets "your approval is needed" when work reaches their review step
  (project boards seed Review with `move_out = external`).

## Queue & tenant context
- Notifications are **queued** (`TenantNotification implements ShouldQueue`) and
  released only **after the DB commit** (`afterCommit()`).
- **Queue storage stays central**: `DB_QUEUE_CONNECTION=pgsql` pins the `jobs`
  table to the central connection even while a tenant's default connection is
  switched. stancl's `QueueTenancyBootstrapper` serializes the `tenant_id` into
  the job and **re-initializes the tenant** when the worker runs it, so in-app
  notifications and `email_logs` are written to the correct tenant database.
- **Failed jobs preserve** the tenant id (stancl) and the correlation id (a
  property on every notification, seeded from `AssignRequestId`'s `X-Request-Id`).

## Idempotency
- In-app: `IdempotentDatabaseChannel` derives a deterministic notification id from
  `correlation id + type + related record + recipient`, so a retried queue job
  updates the same row instead of creating a duplicate.
- Email is an external side effect and is best-effort; each attempt is logged.

## Tables (tenant db)
- `notifications` — Laravel's schema.
- `email_logs` — `notification_type, recipient, subject, status, provider_message_id,
  error_message, correlation_id, related_type/related_id, sent_at, timestamps`.
- `notification_preferences` — `user_id, notification_type, email_enabled,
  in_app_enabled` (unique per user+type). A missing row means all channels on.

## Audience rules (external isolation)
- `task_submitted_for_review` → the customer's **external** representatives.
- `task_approved` / `task_rejected` → the **internal** team (assignees + creator),
  never external. Rejection carries the reason.
- `task_assigned` → the newly assigned internal users.
- External reps therefore only ever receive review requests for their own boards.

## Scheduling
- `tasks:notify-due-soon` (hourly) scans every tenant for tasks due within the
  window and notifies internal assignees; a per-task/per-day correlation id keeps
  repeat runs idempotent.

## Adding a notification in a later module
1. Add the `NotificationType` case (already present for Job/Workshop/Quotation).
2. Extend `TenantNotification`; implement `type()`, `arrayData()`, `toMail()`
   (use `brandedMail()` for tenant branding). Set `relatedType()/relatedId()`.
3. Dispatch via `Notification::send($recipients, new YourNotification(...))` from a
   listener on your domain event (which should itself fire after commit).
