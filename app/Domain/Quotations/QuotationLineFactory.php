<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Quotations\Enums\DiscountType;
use App\Domain\Quotations\Enums\LineType;
use App\Domain\Settings\TenantSettings;
use App\Support\Money;

/**
 * Builds a quotation line's persisted attributes, resolving the snapshot fields
 * (internal name, cost, price, tax) at quotation time so later inventory changes
 * never alter historical quotations. Money is computed in minor units.
 */
class QuotationLineFactory
{
    public function __construct(
        private TenantSettings $settings,
        private QuotationCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function make(array $input, int $index): array
    {
        $item = ! empty($input['inventory_item_id'])
            ? InventoryItem::where('public_id', $input['inventory_item_id'])->first()
            : null;

        $unitPrice = $this->resolveUnitPrice($input, $item);
        $quantity = (float) ($input['quantity'] ?? 1);
        $taxRate = array_key_exists('tax_rate', $input) && $input['tax_rate'] !== null && $input['tax_rate'] !== ''
            ? (float) $input['tax_rate']
            : $this->settings->quotationDefaultTaxRate();

        $discountType = ! empty($input['discount_type']) ? DiscountType::from($input['discount_type']) : null;
        $discountValue = (float) ($input['discount_value'] ?? 0);

        $money = $this->calculator->line($unitPrice, $quantity, $discountType, $discountValue, $taxRate);

        return [
            'inventory_item_id' => $item?->id,
            'line_type' => ($item !== null ? LineType::Item : LineType::Custom)->value,
            'internal_name_snapshot' => $item?->name,
            'customer_alias' => $input['customer_alias'] ?? ($item !== null ? $item->name : 'Item'),
            'description' => $input['description'] ?? null,
            'quantity' => $quantity,
            'unit' => $input['unit'] ?? ($item !== null ? $item->unit : 'unit'),
            'unit_cost_snapshot' => $item !== null ? $this->itemCostMinor($item) : null,
            'unit_price' => $unitPrice,
            'discount_type' => $discountType?->value,
            'discount_value' => $discountType !== null ? $discountValue : null,
            'tax_rate' => $taxRate,
            ...$money,
            'sort_order' => (int) ($input['sort_order'] ?? $index),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveUnitPrice(array $input, ?InventoryItem $item): int
    {
        if (array_key_exists('unit_price', $input) && $input['unit_price'] !== null && $input['unit_price'] !== '') {
            return Money::toMinor($input['unit_price']);
        }

        return $item?->current_sale_price !== null ? Money::toMinor($item->current_sale_price) : 0;
    }

    /**
     * Best-available cost snapshot: latest purchase cost, else preferred supplier price.
     */
    private function itemCostMinor(InventoryItem $item): ?int
    {
        $lastPurchase = $item->movements()
            ->where('type', MovementType::Purchase->value)
            ->whereNotNull('unit_cost')->latest('id')->value('unit_cost');

        if ($lastPurchase !== null) {
            return Money::toMinor($lastPurchase);
        }

        $supplierPrice = $item->suppliers()
            ->orderByDesc('inventory_item_suppliers.is_preferred')
            ->value('inventory_item_suppliers.last_purchase_price');

        return $supplierPrice !== null ? Money::toMinor($supplierPrice) : null;
    }
}
