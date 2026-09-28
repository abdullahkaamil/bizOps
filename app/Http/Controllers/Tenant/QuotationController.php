<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Quotations\Actions\CreateQuotationAction;
use App\Domain\Quotations\Actions\UpdateQuotationAction;
use App\Domain\Quotations\Enums\DiscountType;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationLine;
use App\Domain\Quotations\Models\QuotationStatusHistory;
use App\Domain\Settings\TenantSettings;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreQuotationRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->with('customer')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Quotation $q): array => [
                'id' => $q->public_id,
                'number' => $q->number,
                'customer' => $q->customer?->company_name,
                'status' => $q->status->value,
                'grand_total' => Money::toMajor($q->grand_total),
                'currency' => $q->currency,
                'issue_date' => $q->issue_date->toDateString(),
                'valid_until' => $q->valid_until?->toDateString(),
            ]);

        return Inertia::render('quotations/Index', [
            'quotations' => $quotations->all(),
            'canCreate' => $request->user()->can('create', Quotation::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Quotation::class);

        return Inertia::render('quotations/Create', $this->formData());
    }

    public function store(StoreQuotationRequest $request, CreateQuotationAction $action): RedirectResponse
    {
        $this->authorize('create', Quotation::class);

        $quotation = $action->handle($this->resolveData($request->validated()), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation created.')]);

        return to_route('tenant.quotations.show', $quotation);
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        $this->authorize('view', $quotation);

        $quotation->load(['customer', 'contact', 'lines.item', 'statusHistory.actor']);
        $canViewCost = $request->user()->can('viewCost', Quotation::class);

        return Inertia::render('quotations/Show', [
            'quotation' => $this->serialize($quotation, $canViewCost),
            'canViewCost' => $canViewCost,
            'documents' => GeneratedDocument::query()
                ->where('related_type', Quotation::class)->where('related_id', $quotation->id)
                ->latest('id')->get()
                ->map(fn (GeneratedDocument $d): array => [
                    'id' => $d->public_id, 'type' => $d->document_type->label(),
                    'number' => $d->number, 'url' => $d->temporaryDownloadUrl(),
                ])->all(),
            'abilities' => [
                'edit' => $quotation->status->isEditable() && $request->user()->can('update', $quotation),
                'send' => $quotation->status === QuotationStatus::Draft && $request->user()->can('send', $quotation),
                'decide' => $quotation->status === QuotationStatus::Sent && $request->user()->can('decide', $quotation),
                'cancel' => in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Sent], true) && $request->user()->can('cancel', $quotation),
                'duplicate' => $request->user()->can('create', Quotation::class),
            ],
            // Copyable public accept/reject link — only meaningful while the quote
            // is awaiting the customer's decision (no mail server needed).
            'reviewLink' => $quotation->status === QuotationStatus::Sent
                ? URL::signedRoute('tenant.quotations.review', ['quotation' => $quotation->public_id])
                : null,
        ]);
    }

    public function edit(Request $request, Quotation $quotation): Response
    {
        $this->authorize('update', $quotation);

        abort_unless($quotation->status->isEditable(), 403);

        $quotation->load(['lines.item', 'customer']);

        return Inertia::render('quotations/Edit', [
            ...$this->formData(),
            'quotation' => $this->serialize($quotation, true),
        ]);
    }

    public function update(StoreQuotationRequest $request, Quotation $quotation, UpdateQuotationAction $action): RedirectResponse
    {
        $this->authorize('update', $quotation);

        $action->handle($quotation, $this->resolveData($request->validated()), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation updated.')]);

        return to_route('tenant.quotations.show', $quotation);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function resolveData(array $validated): array
    {
        $customerId = Customer::where('public_id', $validated['customer_id'])->value('id');
        $contactId = ! empty($validated['customer_contact_id'])
            ? CustomerContact::where('public_id', $validated['customer_contact_id'])->value('id')
            : null;

        return [
            'customer_id' => $customerId,
            'customer_contact_id' => $contactId,
            'currency' => $validated['currency'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'lines' => $validated['lines'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Quotation $quotation, bool $canViewCost): array
    {
        return [
            'id' => $quotation->public_id,
            'number' => $quotation->number,
            'status' => $quotation->status->value,
            'currency' => $quotation->currency,
            'issue_date' => $quotation->issue_date->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString(),
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'customer' => $quotation->customer ? [
                'id' => $quotation->customer->public_id, 'name' => $quotation->customer->company_name,
            ] : null,
            'contact_id' => $quotation->contact?->public_id,
            'subtotal' => Money::toMajor($quotation->subtotal),
            'discount_total' => Money::toMajor($quotation->discount_total),
            'tax_total' => Money::toMajor($quotation->tax_total),
            'grand_total' => Money::toMajor($quotation->grand_total),
            'lines' => $quotation->lines->map(fn (QuotationLine $l): array => [
                'id' => $l->public_id,
                'item_id' => $l->item?->public_id,
                // Internal name + cost only for authorized internal users.
                'internal_name' => $canViewCost ? $l->internal_name_snapshot : null,
                'unit_cost' => $canViewCost && $l->unit_cost_snapshot !== null ? Money::toMajor($l->unit_cost_snapshot) : null,
                'customer_alias' => $l->customer_alias,
                'description' => $l->description,
                'quantity' => $l->quantity,
                'unit' => $l->unit,
                'unit_price' => Money::toMajor($l->unit_price),
                'discount_type' => $l->discount_type?->value,
                'discount_value' => $l->discount_value,
                'tax_rate' => $l->tax_rate,
                'line_total' => Money::toMajor($l->line_total),
                'sort_order' => $l->sort_order,
            ])->all(),
            'history' => $quotation->relationLoaded('statusHistory')
                ? $quotation->statusHistory->map(fn (QuotationStatusHistory $h): array => [
                    'id' => $h->public_id,
                    'from' => $h->from_status?->value,
                    'to' => $h->to_status->value,
                    'reason' => $h->reason,
                    'actor' => $h->actor?->name,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        return [
            'customers' => Customer::query()->orderBy('company_name')->get()
                ->map(fn (Customer $c): array => [
                    'id' => $c->public_id,
                    'name' => $c->company_name,
                    'contacts' => $c->contacts()->get(['public_id', 'first_name', 'last_name'])
                        ->map(fn ($ct): array => ['id' => $ct->public_id, 'name' => trim($ct->first_name.' '.$ct->last_name)])->all(),
                ])->all(),
            'items' => InventoryItem::query()->where('status', 'active')->orderBy('name')
                ->get(['public_id', 'sku', 'name', 'unit', 'current_sale_price'])
                ->map(fn (InventoryItem $i): array => [
                    'id' => $i->public_id,
                    'name' => $i->name,
                    'sku' => $i->sku,
                    'unit' => $i->unit,
                    'sale_price' => $i->current_sale_price,
                ])->all(),
            'discountTypes' => DiscountType::values(),
            'defaultTaxRate' => app(TenantSettings::class)->quotationDefaultTaxRate(),
            'currencies' => Currency::options(),
            'defaultCurrency' => app(TenantSettings::class)->currency(),
        ];
    }

    protected function currentUser(Request $request): User
    {
        return $request->user();
    }
}
