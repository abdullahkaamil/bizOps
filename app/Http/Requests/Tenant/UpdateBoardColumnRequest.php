<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Tasks\Enums\ColumnAccess;
use App\Domain\Tasks\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardColumnRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', Rule::in(TaskStatus::values())],
            'move_in' => ['sometimes', Rule::in(ColumnAccess::values())],
            'move_out' => ['sometimes', Rule::in(ColumnAccess::values())],
        ];
    }
}
