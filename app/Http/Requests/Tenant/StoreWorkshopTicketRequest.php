<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkshopTicketRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'serial_number_unavailable' => $this->boolean('serial_number_unavailable'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $needsDeviceDetails = ! $this->filled('device_id');
        $customerId = Customer::query()
            ->where('public_id', $this->string('customer_id')->toString())
            ->value('id');

        return [
            'customer_id' => ['required', Rule::exists((new Customer)->getTable(), 'public_id')],
            'device_id' => ['nullable', Rule::exists((new CustomerDevice)->getTable(), 'public_id')],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'serial_number_unavailable' => ['boolean'],
            'brand' => [Rule::requiredIf($needsDeviceDetails), 'nullable', 'string', 'max:255'],
            'model' => [Rule::requiredIf($needsDeviceDetails), 'nullable', 'string', 'max:255'],
            'issue_description' => ['required', 'string', 'max:10000'],
            'assigned_user_id' => ['nullable', Rule::exists((new User)->getTable(), 'public_id')],
            'job_id' => [
                'nullable',
                Rule::exists((new Job)->getTable(), 'public_id')
                    ->where('customer_id', $customerId)
                    ->whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value]),
            ],
        ];
    }
}
