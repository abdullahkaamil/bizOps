<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Authorization\RoleCatalog;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'user_type' => ['required', Rule::in(array_map(fn (UserType $t) => $t->value, UserType::cases()))],
            'role' => ['required', Rule::in(RoleCatalog::names()->all())],
            'customer_id' => [
                Rule::requiredIf(fn (): bool => $this->input('user_type') === UserType::External->value),
                'nullable',
                Rule::exists('customers', 'public_id'),
            ],
            'department_id' => ['nullable', Rule::exists('departments', 'public_id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $role = (string) $this->input('role');
            $userType = UserType::tryFrom((string) $this->input('user_type'));

            // The role must match the user type (external users only get the
            // external role; internal users only get internal roles).
            if ($role !== '' && $userType !== null && RoleCatalog::userTypeFor($role) !== $userType) {
                $validator->errors()->add('role', __('This role is not compatible with the selected user type.'));
            }

            // Internal users must not be tied to a customer.
            if ($userType === UserType::Internal && $this->filled('customer_id')) {
                $validator->errors()->add('customer_id', __('Internal users cannot be linked to a customer.'));
            }
        });
    }
}
