<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permission::values())],
        ];
    }
}
