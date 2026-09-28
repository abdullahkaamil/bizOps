# Privacy & Data Handling

## Data categories
- **Account data** — user name, email, role, login timestamps.
- **Personal data (customers/contacts)** — names, emails, phones, addresses.
- **Signatures** — customer sign-off images (private files).
- **Photos** — job/workshop images (private files, metadata stripped).
- **Email logs** — recipient, subject, status (no message bodies).
- **Operational records** — jobs, tickets, tasks, quotations, stock movements.
- **Audit logs** — actor + action + timestamp.

## Retention & deletion
- Tenant deletion is **staged** with a retention window
  (active→suspended→archived→deletion_pending→deleted); the purge cannot run before
  the retention period elapses, giving an export/backup opportunity.
- Soft-deleted records are excluded from all reads and counts.
- Backups are encrypted and subject to their own retention (see `docs/backups.md`).

## Export & access requests
- The customer 360 profile aggregates a customer's records for internal review.
- A logical per-tenant export exists (`BackupTenant` → encrypted JSON snapshot);
  a customer-facing data-export/DSAR flow is a documented future enhancement.

## Responsibility split
- **Platform (operator)** — infrastructure security, tenant isolation, backups,
  restore testing, encryption at rest/in transit, audit of central actions.
- **Tenant (customer)** — accuracy and lawful basis of the personal data they
  enter, their users' access, and responding to their own end-customers' requests.

> Full GDPR/DSAR automation and a written DPA are on the pre-production checklist
> (decision register X4).
