<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\QuotationCalculator;

/**
 * Recompute every line's money from its stored inputs and roll the totals up onto
 * the quotation. The authoritative, server-side calculation (integer minor units).
 */
class RecalculateQuotationAction
{
    public function __construct(private QuotationCalculator $calculator) {}

    public function handle(Quotation $quotation): Quotation
    {
        $lines = $quotation->lines()->get();

        foreach ($lines as $line) {
            $money = $this->calculator->line(
                $line->unit_price,
                (float) $line->quantity,
                $line->discount_type,
                (float) $line->discount_value,
                (float) $line->tax_rate,
            );
            $line->forceFill($money)->save();
        }

        $quotation->update($this->calculator->totals($lines));

        return $quotation->refresh();
    }
}
