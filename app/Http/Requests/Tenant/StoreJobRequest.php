<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customerId = Customer::query()
            ->where('public_id', $this->string('customer_id')->toString())
            ->value('id');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'customer_id' => ['required', Rule::exists((new Customer)->getTable(), 'public_id')],
            'customer_contact_id' => [
                'nullable',
                Rule::exists((new CustomerContact)->getTable(), 'public_id')->where('customer_id', $customerId),
            ],
            'service_address_id' => [
                'nullable',
                Rule::exists((new CustomerAddress)->getTable(), 'public_id')->where('customer_id', $customerId),
            ],
            'assigned_user_id' => ['nullable', Rule::exists((new User)->getTable(), 'public_id')],
            'planned_at' => ['nullable', 'date'],
        ];
    }
}
