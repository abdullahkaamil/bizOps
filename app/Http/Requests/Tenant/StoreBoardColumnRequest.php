<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Tasks\Enums\ColumnAccess;
use App\Domain\Tasks\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardColumnRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The workflow bucket the step maps to; defaults to "in progress".
            'category' => ['sometimes', Rule::in(TaskStatus::values())],
            // Who may move cards into / out of this step (defaults to both).
            'move_in' => ['sometimes', Rule::in(ColumnAccess::values())],
            'move_out' => ['sometimes', Rule::in(ColumnAccess::values())],
        ];
    }
}
