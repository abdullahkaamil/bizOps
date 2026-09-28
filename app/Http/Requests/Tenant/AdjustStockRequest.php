<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(MovementType::adjustableValues())],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'warehouse_id' => ['nullable', Rule::exists((new Warehouse)->getTable(), 'public_id')],
        ];
    }
}
