<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\Exceptions\InvalidQuotationTransition;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\QuotationLineFactory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Update a DRAFT quotation. Sent or terminal quotations are locked. Lines are
 * rebuilt (re-snapshotted) and the totals recomputed server-side.
 */
class UpdateQuotationAction
{
    public function __construct(
        private QuotationLineFactory $lineFactory,
        private RecalculateQuotationAction $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Quotation $quotation, array $data, User $actor): Quotation
    {
        if (! $quotation->status->isEditable()) {
            throw InvalidQuotationTransition::locked();
        }

        return DB::transaction(function () use ($quotation, $data, $actor): Quotation {
            $quotation->update([
                'customer_contact_id' => $data['customer_contact_id'] ?? $quotation->customer_contact_id,
                'currency' => $data['currency'] ?? $quotation->currency,
                'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            // Rebuild lines from scratch (still a draft, so re-snapshotting is fine).
            $quotation->lines()->delete();
            foreach (array_values($data['lines'] ?? []) as $index => $lineInput) {
                $quotation->lines()->create($this->lineFactory->make($lineInput, $index));
            }

            activity('quotation')->performedOn($quotation)->causedBy($actor)->event('updated')->log('quotation.updated');

            return $this->recalculate->handle($quotation);
        });
    }
}
