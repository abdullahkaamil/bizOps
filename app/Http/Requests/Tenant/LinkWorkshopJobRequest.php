<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Workshop\Models\WorkshopTicket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkWorkshopJobRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $customerId = $ticket instanceof WorkshopTicket
            ? $ticket->device()->value('customer_id')
            : null;

        return [
            'job_id' => [
                'required',
                Rule::exists((new Job)->getTable(), 'public_id')
                    ->where('customer_id', $customerId)
                    ->whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value]),
            ],
        ];
    }
}
