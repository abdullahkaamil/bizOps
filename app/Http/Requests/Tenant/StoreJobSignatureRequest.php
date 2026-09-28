<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobSignatureRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimetypes:image/png,image/webp', 'max:2048'],
            'signed_by_name' => ['nullable', 'string', 'max:255'],
            'signed_by_role' => ['nullable', 'string', 'max:255'],
        ];
    }
}
