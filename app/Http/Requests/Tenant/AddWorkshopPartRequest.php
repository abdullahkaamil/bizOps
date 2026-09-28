<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Inventory\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddWorkshopPartRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', Rule::exists((new InventoryItem)->getTable(), 'public_id')],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
