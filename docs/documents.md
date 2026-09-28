# Documents & Media

Shared, tenant-aware document generation and secure download (Phase 14). Jobs and
later modules request branded PDFs without owning any mail/PDF plumbing.

## Architecture
- **`DocumentGenerator`** (interface) — one implementation **per document type**,
  never one giant conditional template.
- **`AbstractDocumentGenerator`** — the shared pipeline: render a Blade template
  to HTML → convert to PDF → store privately (tenant-prefixed path) → record a
  `GeneratedDocument` (checksum, size, metadata, number, generator).
  `renderHtml()` is exposed so template concerns (required fields, pagination,
  missing optional assets) are unit-testable without a PDF binary.
- **`PdfRenderer`** (interface) → **`DompdfRenderer`** (pure-PHP dompdf). Remote
  loading is **disabled**; the logo, photos, and signature are embedded as
  `data:` URIs by the generator so nothing private is fetched over the network.
- **`DocumentContext`** carries the source record, the acting user, and the
  tenant's `TenantSettings` (branding + localization).
- **`DocumentService`** — registry mapping `DocumentType` → generator; `generate()`
  runs inline, `queue()` dispatches `GenerateDocument` (tenant-aware, for large
  PDFs).

## Document types
`job_service_report` (implemented), `workshop_completion_report`,
`workshop_delivery_report`, `quotation`, `customer_export` (registered as their
modules land — each just adds a generator + Blade template).

## `generated_documents` (tenant db)
`document_type, related_type/related_id (morph), number, disk, path, mime_type,
size_bytes, checksum, generated_by, generated_at, metadata (jsonb)`.

## Security
- Files are **private** (`local` disk), never publicly served.
- The path is built server-side as `documents/{tenant-id}/{type}/{uuid}.pdf` —
  **never from request input** — giving each tenant its own prefix.
- Download route is **`signed`** (temporary URL, `GeneratedDocument::temporaryDownloadUrl()`)
  **and** policy-checked (`GeneratedDocumentPolicy`): internal staff need the
  source module's view permission; external reps may only download customer-facing
  types (`quotation`, `customer_export`) belonging to their own customer.
- Tenant context is guaranteed by the subdomain tenancy middleware and by the
  document row living in the tenant database (cross-tenant ids simply 404).

## Job service report content
Tenant logo + legal details, form number, date, customer, service address,
planned/actual-start/actual-end times, server-computed duration, service notes
(paginating), embedded photo grid, related workshop devices (placeholder until
Workshop), signature, and delivery/acceptance labels.

## Adding a document type in a later module
1. Add the `DocumentType` case (already present for workshop/quotation/export).
2. Add a Blade template under `resources/views/documents/`.
3. Extend `AbstractDocumentGenerator` (implement `type/view/viewData`, optionally
   `number/metadata/paper`), register it in `DocumentService::GENERATORS`.
4. Map the type → permission in `GeneratedDocumentPolicy::permissionFor()`.
