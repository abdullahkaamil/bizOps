<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Documents\NextDocumentNumberService;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationStatusHistory;
use App\Domain\Settings\TenantSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Duplicate any quotation into a fresh DRAFT, copying the header and the line
 * snapshots verbatim (a new number, cleared lifecycle timestamps).
 */
class DuplicateQuotationAction
{
    public function __construct(
        private NextDocumentNumberService $numbers,
        private TenantSettings $settings,
    ) {}

    public function handle(Quotation $quotation, User $actor): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor): Quotation {
            $copy = Quotation::create([
                'number' => $this->numbers->next('quotation', $this->settings->quotationNumberPrefix()),
                'customer_id' => $quotation->customer_id,
                'customer_contact_id' => $quotation->customer_contact_id,
                'status' => QuotationStatus::Draft,
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays($this->settings->quotationValidityDays())->toDateString(),
                'currency' => $quotation->currency,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'subtotal' => $quotation->subtotal,
                'discount_total' => $quotation->discount_total,
                'tax_total' => $quotation->tax_total,
                'grand_total' => $quotation->grand_total,
                'created_by' => $actor->id,
            ]);

            foreach ($quotation->lines()->get() as $line) {
                $attributes = $line->only([
                    'inventory_item_id', 'line_type', 'internal_name_snapshot', 'customer_alias',
                    'description', 'quantity', 'unit', 'unit_cost_snapshot', 'unit_price',
                    'discount_type', 'discount_value', 'tax_rate',
                    'line_subtotal', 'line_discount', 'line_tax', 'line_total', 'sort_order',
                ]);
                $copy->lines()->create($attributes);
            }

            QuotationStatusHistory::create([
                'quotation_id' => $copy->id,
                'from_status' => null,
                'to_status' => QuotationStatus::Draft,
                'actor_id' => $actor->id,
                'reason' => "Duplicated from {$quotation->number}",
                'created_at' => now(),
            ]);

            activity('quotation')->performedOn($copy)->causedBy($actor)->event('duplicated')->log('quotation.duplicated');

            return $copy->refresh();
        });
    }
}
