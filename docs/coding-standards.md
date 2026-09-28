# Coding Standards

Full rules live in the engineering-rules reference; this is the working summary. Run `composer ci:check` before finishing any change.

## PHP
- `declare(strict_types=1)` in new files; explicit param + return types; typed properties.
- Constructor property promotion; curly braces on all control structures.
- PHP **enums** for finite business statuses; TitleCase enum cases.
- **Action classes** for state transitions and multi-step operations; **never** accept a raw status change from the client.
- **Form Requests** for validation; **Policies** for authorization (server-side is the only real gate).
- Wrap multi-record business operations in **DB transactions**.
- No business logic in controllers or in Eloquent model event hooks (unless unavoidable).
- Deliberate eager loading; prevent N+1. Prefer query objects (`Domain/<Module>/Queries`) for complex reads.
- Events only when decoupling is genuinely valuable.
- Format with **Pint** (`vendor/bin/pint`); static analysis with **Larastan** at the configured level (0 errors).
- Prefer `php artisan make:` generators; keep to the existing directory structure.

## Vue / TypeScript
- Vue 3 `<script setup lang="ts">`, single root element per component.
- Type page props, forms, enums, and API responses. Shared page props are typed in `resources/js/types`.
- **Inertia forms** for normal submissions; JSON endpoints only for highly interactive controls.
- Minimal shared state; Pinia only when state must survive across unrelated pages.
- Use server-provided permissions (`usePermissions().can()`) to hide/disable controls — presentation only.
- Every list/detail provides **loading, empty, error, disabled** states.
- Job + signature flows are touch-friendly; forms are keyboard accessible; never use color alone for status (pair with text/icon).
- Lint with ESLint, format with Prettier, type-check with `vue-tsc`.

## Testing
Each business module ships: unit (business rules), feature (endpoints), policy, tenant-isolation, state-transition matrix, validation, queue, and file-authorization tests, plus ≥1 e2e happy-path for critical workflows. Tenancy tests that provision real databases live in the `Tenancy` suite (no `RefreshDatabase` transaction — Postgres cannot `CREATE DATABASE` in a transaction) and clean up tenant DBs in `afterEach`.

## Codex / AI-agent prompt conventions
- Work **one phase per branch**; do not attempt the whole roadmap in one pass.
- Before coding a phase, restate its Scope / Backend / Frontend / Authorization / Tests / DoD / Exclusions.
- A phase is done only when its tests **and** definition of done pass; update `docs/implementation-status.md` in the same change.
- Follow the decision register; never generate code from an unresolved assumption without adding a register row.
