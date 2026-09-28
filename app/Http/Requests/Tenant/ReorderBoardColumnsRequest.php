<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Tasks\Models\BoardColumn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderBoardColumnsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['uuid', Rule::exists((new BoardColumn)->getTable(), 'public_id')],
        ];
    }
}
