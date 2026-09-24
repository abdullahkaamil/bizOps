<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Authorization\RoleCatalog;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::defaults()],
            // Direct creation is for internal users only; external customer
            // representatives must be invited (they require a customer link).
            'role' => ['required', 'string', Rule::in(RoleCatalog::assignableFor(UserType::Internal))],
        ];
    }
}
