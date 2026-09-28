<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Inventory\Enums\InventoryItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $ignoreId = $item instanceof InventoryItem ? $item->id : null;

        return [
            'sku' => ['required', 'string', 'max:100', Rule::unique((new InventoryItem)->getTable(), 'sku')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in(InventoryItemStatus::values())],
            'current_sale_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
