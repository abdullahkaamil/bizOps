<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\Tasks\Enums\BoardType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(BoardType::values())],
            // A project board is tied to a customer; an internal board is not.
            'customer_id' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === BoardType::Project->value),
                Rule::prohibitedIf(fn (): bool => $this->input('type') !== BoardType::Project->value),
                'nullable',
                Rule::exists((new Customer)->getTable(), 'public_id'),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
