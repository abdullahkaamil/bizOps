<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Inventory\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachSupplierRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_preferred' => $this->boolean('is_preferred')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists((new Supplier)->getTable(), 'public_id')],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'last_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_preferred' => ['boolean'],
        ];
    }
}
