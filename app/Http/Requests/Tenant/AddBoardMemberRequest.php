<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddBoardMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                Rule::exists((new User)->getTable(), 'public_id')->where('status', 'active'),
            ],
            'role' => ['nullable', 'string', 'max:50'],
        ];
    }
}
