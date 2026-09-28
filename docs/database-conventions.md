# Database Conventions

## Primary-key strategy
- **Central `tenants`**: string UUID primary key (stancl).
- **Tenant tables**: auto-increment `bigIncrements` internal PK for relationships and performance.

## UUID policy
Expose a **public UUID** (`public_id`, indexed, unique) on customer-/API-facing tenant records instead of leaking sequential internal IDs. Route-model-bind and serialize by `public_id`; keep the bigint PK internal. (Applied per module as they are built.)

## Timestamps
`created_at` / `updated_at` on all tables. Store **UTC** everywhere; convert to the tenant timezone only for display.

## Soft deletes
`deleted_at` on recoverable business records (customers, tasks, jobs, tickets, quotations, products). **Never** soft-delete immutable audit or ledger rows.

## Foreign-key naming
`<singular>_id` (e.g. `customer_id`, `assigned_user_id`). Always add a real FK constraint with an appropriate `onDelete` (restrict for referenced masters, cascade only where a child cannot outlive its parent).

## Index policy
Index every FK column and every common filter/sort column (status, dates, `public_id`). Add composite indexes for frequent multi-column filters. Add unique constraints where a business rule requires uniqueness (e.g. one default address per customer, product SKU per tenant).

## Money representation
**Never floating-point for money.** Two exact representations are used, by role:

- **Transactional totals** (customer-facing quotation money: `subtotal`, `discount_total`, `tax_total`, `grand_total`, `unit_price`, `line_total`, `unit_cost_snapshot`, …) are stored as **integer minor units** in `bigInteger` columns and computed through the `App\Support\Money` value object + `QuotationCalculator`, which does all arithmetic in integer minor units and only ever `(int) round(...)`s at the boundary.
- **Catalog / ledger per-unit prices** (inventory `current_sale_price`, `last_purchase_price`, stock-movement `unit_cost`, workshop-part `unit_cost_snapshot`/`sale_price_snapshot`) are stored as exact `decimal(12,2)` — PostgreSQL `numeric` is exact, not float. These are cast `decimal:2` (PHP **string**, never float) and cross into the quotation totals path via `Money::toMinor()` (exact string→minor-int). Money never travels through a PHP `(float)` cast.

**Quantities are not money** and use `decimal(14,3)`; their arithmetic may use float (the stock balance is a cache, reconcilable from the append-only ledger sum). Rates/values (`tax_rate`, `discount_value`) are `decimal` too. Currency is a per-tenant single value (Q1); prices are stored **net** (VAT-exclusive, I7), tax applied at quotation time; quotations snapshot unit prices at send.

## Serial numbers
Serial-tracked records (`customer_devices`) use `serial_number` **nullable** plus a `serial_number_unavailable` boolean, with a **partial unique index** on non-null serials (`WHERE serial_number IS NOT NULL AND deleted_at IS NULL`) — so many devices may have "no serial" while real serials stay unique.

## Timezone handling
`config('app.timezone')` is **UTC**; the DB stores UTC. Tenant timezone lives in `tenant_settings` (`localization.timezone`, default `UTC`) and is applied only for **display / PDF** via `TenantSettings::formatFor()` (`setTimezone(tenant tz)`). Lifecycle timestamps that must be authoritative (job `actual_start_at`/end, ticket received/completed/delivered) are stamped **server-side** with `now()`, never from client input.

## JSON usage
Use JSON/JSONB for genuinely schemaless or snapshot payloads (e.g. tenant `data`, price snapshots, flexible metadata). Do not use JSON for data you need to query/filter relationally — model those as columns/tables.

## File metadata conventions
Store file metadata in a `documents`/attachments table: owner (polymorphic), disk, path, original filename, mime, size, `uploaded_by`, timestamps. Binary lives on the tenant disk (local/S3); access is via signed private URLs. Never trust client-supplied paths.

## Tenant settings & document numbers
Tenant company configuration uses a single flexible **EAV table** `tenant_settings(group, key, value jsonb, is_encrypted)` (unique `group+key`), not many strongly-typed tables — accessed through the typed `App\Domain\Settings\TenantSettings` service with documented defaults; `is_encrypted` rows are encrypted at rest. Document numbers come from `document_sequences(type, year, prefix, current_number, padding)` via `App\Domain\Documents\NextDocumentNumberService`, which locks the counter row `FOR UPDATE` in a transaction (e.g. `JOB-2026-000001`).

## Accountability
Add `created_by` / `updated_by` (and actor history tables) where accountability matters. Important state changes get history rows; stock changes get an **immutable ledger** (append-only, never updated or soft-deleted).
