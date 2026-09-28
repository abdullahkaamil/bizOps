<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Domain\Quotations\Enums\DiscountType;
use App\Domain\Quotations\Models\QuotationLine;
use App\Support\Money;

/**
 * Authoritative, server-side quotation maths. Everything is computed in integer
 * minor units — no floating-point arithmetic on money. Client previews may
 * approximate; these results are the ones persisted.
 */
class QuotationCalculator
{
    /**
     * Compute a single line's money fields from its inputs.
     *
     * @param  int  $unitPriceMinor  price per unit in minor units
     * @return array{line_subtotal: int, line_discount: int, line_tax: int, line_total: int}
     */
    public function line(
        int $unitPriceMinor,
        float $quantity,
        ?DiscountType $discountType,
        float $discountValue,
        float $taxRate,
    ): array {
        $subtotal = (int) round($quantity * $unitPriceMinor);

        $discount = match ($discountType) {
            DiscountType::Percent => (int) round($subtotal * $discountValue / 100),
            DiscountType::Fixed => min(Money::toMinor($discountValue), $subtotal),
            null => 0,
        };
        $discount = max(0, min($discount, $subtotal));

        $taxable = $subtotal - $discount;
        $tax = (int) round($taxable * $taxRate / 100);

        return [
            'line_subtotal' => $subtotal,
            'line_discount' => $discount,
            'line_tax' => $tax,
            'line_total' => $taxable + $tax,
        ];
    }

    /**
     * Sum computed line totals into quotation totals.
     *
     * @param  iterable<QuotationLine>  $lines
     * @return array{subtotal: int, discount_total: int, tax_total: int, grand_total: int}
     */
    public function totals(iterable $lines): array
    {
        $subtotal = $discount = $tax = $total = 0;

        foreach ($lines as $line) {
            $subtotal += $line->line_subtotal;
            $discount += $line->line_discount;
            $tax += $line->line_tax;
            $total += $line->line_total;
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'grand_total' => $total,
        ];
    }
}
