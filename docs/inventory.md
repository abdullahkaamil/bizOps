# Inventory & Suppliers

Ledger-based stock, pricing, supplier history, and workshop part consumption
(Phase 16).

## Stock is a ledger, not a column
Quantities are never stored on the item. The source of truth is the append-only
`stock_movements` table; a **warehouse balance is `SUM(quantity)`** (quantity is a
signed delta — positive into stock, negative out). `inventory_stock_balances` is a
**cache** maintained inside every stock transaction and must reconcile with the
ledger (`StockLedger::reconciledBalance`).

## Movement types
`opening`, `purchase`, `adjustment_in`, `adjustment_out`, `workshop_consumption`,
`workshop_return`, `sale`, `return`, `transfer_in`, `transfer_out`. Each carries a
`direction()` (+1/-1) applied to the recorded magnitude.

## The single writer — `StockLedger::record()`
Every movement goes through one method, which inside a transaction:
1. Locks the `(item, warehouse)` balance row `FOR UPDATE`.
2. Enforces the negative-stock policy against the **locked** balance — if the
   tenant disallows negative stock and the result would be `< 0`, throws
   `InsufficientStock`. The row lock is what prevents concurrent consumers from
   overselling.
3. Appends the immutable `StockMovement`.
4. Updates the cached balance.

## Workshop consumption (atomic)
`AddPartToWorkshopTicketAction` wraps, in one transaction: the `workshop_consumption`
movement (validated under lock) **and** the `workshop_ticket_parts` row, which
snapshots `unit_cost_snapshot` (latest purchase cost / preferred supplier price)
and `sale_price_snapshot` so history stays stable when catalogue prices change. If
any step fails, everything rolls back. `RemovePartFromWorkshopTicketAction` records
a reversing `workshop_return` movement and deletes the part row.

## Cost visibility
Purchase cost, supplier `last_purchase_price`, and price history require the
`inventory.view_cost` permission (owners/admins/managers). Technicians hold
`inventory.view` and can select parts, but cost fields are stripped from every
payload (`InventoryPolicy::viewCost`, checked in serialization).

## Suppliers
`inventory_item_suppliers` links items ↔ suppliers with `supplier_sku`,
`last_purchase_price`, `lead_time_days`, and `is_preferred` (unique per pair).

## Warehouses
Multi-warehouse ready; the MVP uses a single default warehouse
(`Warehouse::default()` creates `MAIN` on first use).

## Decisions
See the decision register: **I-ledger** (ledger + cached balance), **I-negative**
(negative stock per tenant setting, enforced under a row lock), **I-cost**
(cost gated by `inventory.view_cost`), **I-warehouse** (single default for MVP).
