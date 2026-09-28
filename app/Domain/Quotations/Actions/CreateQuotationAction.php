<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Documents\NextDocumentNumberService;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationStatusHistory;
use App\Domain\Quotations\QuotationLineFactory;
use App\Domain\Settings\TenantSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a draft quotation with snapshot-preserving lines and server-computed
 * totals.
 */
class CreateQuotationAction
{
    public function __construct(
        private NextDocumentNumberService $numbers,
        private TenantSettings $settings,
        private QuotationLineFactory $lineFactory,
        private RecalculateQuotationAction $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $creator): Quotation
    {
        return DB::transaction(function () use ($data, $creator): Quotation {
            $currency = $data['currency'] ?? $this->settings->currency();

            $quotation = Quotation::create([
                'number' => $this->numbers->next('quotation', $this->settings->quotationNumberPrefix()),
                'customer_id' => $data['customer_id'],
                'customer_contact_id' => $data['customer_contact_id'] ?? null,
                'status' => QuotationStatus::Draft,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays($this->settings->quotationValidityDays())->toDateString(),
                'currency' => $currency,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => $creator->id,
            ]);

            foreach (array_values($data['lines'] ?? []) as $index => $lineInput) {
                $quotation->lines()->create($this->lineFactory->make($lineInput, $index));
            }

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'from_status' => null,
                'to_status' => QuotationStatus::Draft,
                'actor_id' => $creator->id,
                'created_at' => now(),
            ]);

            activity('quotation')->performedOn($quotation)->causedBy($creator)->event('created')->log('quotation.created');

            return $this->recalculate->handle($quotation);
        });
    }
}
