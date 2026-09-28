<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobImageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `image` + `mimetypes` validate the DECODED content, not the extension.
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp,image/heic', 'max:20480'],
        ];
    }
}
