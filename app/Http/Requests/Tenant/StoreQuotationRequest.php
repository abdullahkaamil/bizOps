<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Quotations\Enums\DiscountType;
use App\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists((new Customer)->getTable(), 'public_id')],
            'customer_contact_id' => ['nullable', Rule::exists((new CustomerContact)->getTable(), 'public_id')],
            'currency' => ['nullable', Rule::in(Currency::values())],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', Rule::exists((new InventoryItem)->getTable(), 'public_id')],
            'lines.*.customer_alias' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.discount_type' => ['nullable', Rule::in(DiscountType::values())],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
