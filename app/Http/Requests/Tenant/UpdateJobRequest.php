<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'customer_contact_id' => ['nullable', Rule::exists((new CustomerContact)->getTable(), 'public_id')],
            'service_address_id' => ['nullable', Rule::exists((new CustomerAddress)->getTable(), 'public_id')],
            'planned_at' => ['nullable', 'date'],
        ];
    }
}
